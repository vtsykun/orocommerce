<?php

namespace OroB2B\Bundle\AccountBundle\Visibility\Cache\Product\Category;

use Doctrine\ORM\EntityManager;

use OroB2B\Bundle\AccountBundle\Entity\Visibility\AccountCategoryVisibility;
use OroB2B\Bundle\AccountBundle\Entity\Visibility\CategoryVisibility;
use OroB2B\Bundle\AccountBundle\Entity\Visibility\VisibilityInterface;
use OroB2B\Bundle\AccountBundle\Entity\VisibilityResolved\AccountCategoryVisibilityResolved;
use OroB2B\Bundle\AccountBundle\Entity\VisibilityResolved\BaseCategoryVisibilityResolved;
use OroB2B\Bundle\AccountBundle\Entity\VisibilityResolved\Repository\AccountCategoryRepository;
use OroB2B\Bundle\AccountBundle\Visibility\Cache\Product\AbstractResolvedCacheBuilder;
use OroB2B\Bundle\AccountBundle\Visibility\Cache\Product\Category\Subtree\VisibilityChangeAccountSubtreeCacheBuilder;
use OroB2B\Bundle\WebsiteBundle\Entity\Website;

class AccountProductResolvedCacheBuilder extends AbstractResolvedCacheBuilder
{
    /** @var VisibilityChangeAccountSubtreeCacheBuilder */
    protected $visibilityChangeAccountSubtreeCacheBuilder;

    /**
     * @param VisibilityChangeAccountSubtreeCacheBuilder $visibilityChangeAccountSubtreeCacheBuilder
     */
    public function setVisibilityChangeAccountSubtreeCacheBuilder(
        VisibilityChangeAccountSubtreeCacheBuilder $visibilityChangeAccountSubtreeCacheBuilder
    ) {
        $this->visibilityChangeAccountSubtreeCacheBuilder = $visibilityChangeAccountSubtreeCacheBuilder;
    }

    /**
     * @param VisibilityInterface|AccountCategoryVisibility $visibilitySettings
     */
    public function resolveVisibilitySettings(VisibilityInterface $visibilitySettings)
    {
        $category = $visibilitySettings->getCategory();
        $account = $visibilitySettings->getAccount();

        $selectedVisibility = $visibilitySettings->getVisibility();

        $insert = false;
        $delete = false;
        $update = [];
        $where = ['account' => $account, 'category' => $category];

        $repository = $this->getRepository();

        $hasAccountCategoryVisibilityResolved = $repository->hasEntity($where);

        if (!$hasAccountCategoryVisibilityResolved
            && $selectedVisibility !== AccountCategoryVisibility::ACCOUNT_GROUP
        ) {
            $insert = true;
        }

        if (in_array($selectedVisibility, [AccountCategoryVisibility::HIDDEN, AccountCategoryVisibility::VISIBLE])) {
            $visibility = $this->convertStaticCategoryVisibility($selectedVisibility);
            $update = [
                'visibility' => $visibility,
                'sourceCategoryVisibility' => $visibilitySettings,
                'source' => AccountCategoryVisibilityResolved::SOURCE_STATIC,
            ];
        } elseif ($selectedVisibility == AccountCategoryVisibility::CATEGORY) {
            $visibility = $this->registry
                ->getManagerForClass('OroB2BAccountBundle:VisibilityResolved\CategoryVisibilityResolved')
                ->getRepository('OroB2BAccountBundle:VisibilityResolved\CategoryVisibilityResolved')
                ->getFallbackToAllVisibility($category, $this->getCategoryVisibilityConfigValue());
            $update = [
                'visibility' => $visibility,
                'sourceCategoryVisibility' => $visibilitySettings,
                'source' => AccountCategoryVisibilityResolved::SOURCE_STATIC,
            ];
        } elseif ($selectedVisibility == AccountCategoryVisibility::ACCOUNT_GROUP) {
            if ($account->getGroup()) {
                // Fallback to account group is default for account and should be removed if exist
                if ($hasAccountCategoryVisibilityResolved) {
                    $delete = true;
                }

                $visibility = $this->registry
                    ->getManagerForClass(
                        'OroB2BAccountBundle:VisibilityResolved\AccountGroupCategoryVisibilityResolved'
                    )
                    ->getRepository('OroB2BAccountBundle:VisibilityResolved\AccountGroupCategoryVisibilityResolved')
                    ->getFallbackToGroupVisibility(
                        $category,
                        $account->getGroup(),
                        $this->getCategoryVisibilityConfigValue()
                    );
            } else {
                throw new \LogicException('Impossible set account group visibility to account without account group');
            }
        } elseif ($selectedVisibility == AccountCategoryVisibility::PARENT_CATEGORY) {
            $parentCategory = $category->getParentCategory();
            $visibility = $this->getRepository()->getCategoryVisibility(
                $parentCategory,
                $account,
                $this->getCategoryVisibilityConfigValue()
            );

            $update = [
                'visibility' => $visibility,
                'sourceCategoryVisibility' => $visibilitySettings,
                'source' => AccountCategoryVisibilityResolved::SOURCE_PARENT_CATEGORY,
            ];
        } else {
            throw new \InvalidArgumentException(sprintf('Unknown visibility %s', $selectedVisibility));
        }

        $this->executeDbQuery($repository, $insert, $delete, $update, $where);

        $this->visibilityChangeAccountSubtreeCacheBuilder->resolveVisibilitySettings(
            $category,
            $account,
            $visibility
        );
    }

    /**
     * {@inheritdoc}
     */
    public function isVisibilitySettingsSupported(VisibilityInterface $visibilitySettings)
    {
        return $visibilitySettings instanceof AccountCategoryVisibility;
    }

    /**
     * {@inheritdoc}
     */
    public function buildCache(Website $website = null)
    {
        /** @var AccountCategoryRepository $resolvedRepository */
        $resolvedRepository = $this->registry->getManagerForClass($this->cacheClass)
            ->getRepository($this->cacheClass);

        // clear table
        $resolvedRepository->clearTable();

        // resolve static values
        $resolvedRepository->insertStaticValues($this->insertFromSelectQueryExecutor);

        // resolve values with fallback to category (visibility to all)
        $resolvedRepository->insertCategoryValues($this->insertFromSelectQueryExecutor);

        // resolve parent category values
        $accountVisibilities = $this->indexVisibilities(
            $resolvedRepository->getParentCategoryVisibilities(),
            'visibility_id'
        );
        $accountVisibilityIds = [
            AccountCategoryVisibilityResolved::VISIBILITY_VISIBLE => [],
            AccountCategoryVisibilityResolved::VISIBILITY_HIDDEN => [],
            AccountCategoryVisibilityResolved::VISIBILITY_FALLBACK_TO_CONFIG => [],
        ];
        foreach ($accountVisibilities as $visibilityId => $groupVisibility) {
            $resolvedVisibility = $this->resolveVisibility($accountVisibilities, $groupVisibility);
            $accountVisibilityIds[$resolvedVisibility][] = $visibilityId;
        }
        foreach ($accountVisibilityIds as $visibility => $ids) {
            $resolvedRepository->insertParentCategoryValues($this->insertFromSelectQueryExecutor, $ids, $visibility);
        }
    }

    /**
     * @param array $accountVisibilities
     * @param array $currentGroup
     * @return int
     */
    protected function resolveVisibility(array &$accountVisibilities, array $currentGroup)
    {
        // visibility already resolved
        if (array_key_exists('resolved_visibility', $currentGroup)) {
            return $currentGroup['resolved_visibility'];
        }

        $visibilityId = $currentGroup['visibility_id'];
        $parentVisibility = $currentGroup['parent_visibility'];
        $parentVisibilityId = $currentGroup['parent_visibility_id'];
        $parentGroupVisibilityResolved = $currentGroup['parent_group_resolved_visibility'];
        $parentCategoryVisibilityResolved = $currentGroup['parent_category_resolved_visibility'];

        $resolvedVisibility = null;

        // account group fallback
        if (null === $parentVisibility) {
            // use group visibility if defined, otherwise use category visibility
            $resolvedVisibility = $parentGroupVisibilityResolved !== null
                ? $parentGroupVisibilityResolved
                : $parentCategoryVisibilityResolved;
        // category fallback (visibility to all)
        } elseif ($parentVisibility === AccountCategoryVisibility::CATEGORY) {
            $resolvedVisibility = $parentCategoryVisibilityResolved;
        // parent category fallback
        } elseif ($parentVisibility === AccountCategoryVisibility::PARENT_CATEGORY) {
            $parentGroup = $accountVisibilities[$parentVisibilityId];
            $resolvedVisibility = $this->resolveVisibility($accountVisibilities, $parentGroup);
        // static visibility
        } else {
            $resolvedVisibility
                = $this->convertVisibility($parentVisibility === AccountCategoryVisibility::VISIBLE);
        }

        // config value (default)
        if (null === $resolvedVisibility) {
            $resolvedVisibility = AccountCategoryVisibilityResolved::VISIBILITY_FALLBACK_TO_CONFIG;
        }

        $accountVisibilities[$visibilityId]['resolved_visibility'] = $resolvedVisibility;

        return $resolvedVisibility;
    }

    /**
     * @return EntityManager
     */
    protected function getEntityManager()
    {
        return $this->registry
            ->getManagerForClass('OroB2BAccountBundle:VisibilityResolved\AccountCategoryVisibilityResolved');
    }

    /**
     * @return AccountCategoryRepository
     */
    protected function getRepository()
    {
        return $this->getEntityManager()
            ->getRepository('OroB2BAccountBundle:VisibilityResolved\AccountCategoryVisibilityResolved');
    }

    /**
     * @param string $selectedVisibility
     * @return int
     */
    protected function convertStaticCategoryVisibility($selectedVisibility)
    {
        return ($selectedVisibility == CategoryVisibility::VISIBLE)
            ? BaseCategoryVisibilityResolved::VISIBILITY_VISIBLE
            : BaseCategoryVisibilityResolved::VISIBILITY_HIDDEN;
    }

    /**
     * @return int
     */
    protected function getCategoryVisibilityConfigValue()
    {
        return ($this->configManager->get('oro_b2b_account.category_visibility') == CategoryVisibility::HIDDEN)
            ? BaseCategoryVisibilityResolved::VISIBILITY_HIDDEN
            : BaseCategoryVisibilityResolved::VISIBILITY_VISIBLE;
    }
}
