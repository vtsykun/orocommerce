<?php

namespace Oro\Bundle\ShoppingListBundle\Manager;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\ObjectRepository;

use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

use Oro\Bundle\CustomerBundle\Entity\AccountUser;
use Oro\Bundle\ProductBundle\Rounding\QuantityRoundingService;
use Oro\Bundle\ShoppingListBundle\Entity\LineItem;
use Oro\Bundle\ShoppingListBundle\Entity\ShoppingList;
use Oro\Bundle\ShoppingListBundle\Entity\Repository\LineItemRepository;
use Oro\Bundle\ShoppingListBundle\Entity\Repository\ShoppingListRepository;
use Oro\Bundle\PricingBundle\Manager\UserCurrencyManager;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\WebsiteBundle\Manager\WebsiteManager;

class ShoppingListManager
{
    /**
     * @var AccountUser
     */
    private $accountUser;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var ManagerRegistry
     */
    private $managerRegistry;

    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    /**
     * @var QuantityRoundingService
     */
    protected $rounding;

    /**
     * @var UserCurrencyManager
     */
    protected $userCurrencyManager;

    /**
     * @var WebsiteManager
     */
    protected $websiteManager;

    /**
     * @var ShoppingListTotalManager
     */
    protected $totalManager;

    /**
     * @param ManagerRegistry $managerRegistry
     * @param TokenStorageInterface $tokenStorage
     * @param TranslatorInterface $translator
     * @param QuantityRoundingService $rounding
     * @param UserCurrencyManager $userCurrencyManager
     * @param WebsiteManager $websiteManager
     * @param ShoppingListTotalManager $totalManager
     */
    public function __construct(
        ManagerRegistry $managerRegistry,
        TokenStorageInterface $tokenStorage,
        TranslatorInterface $translator,
        QuantityRoundingService $rounding,
        UserCurrencyManager $userCurrencyManager,
        WebsiteManager $websiteManager,
        ShoppingListTotalManager $totalManager
    ) {
        $this->managerRegistry = $managerRegistry;
        $this->tokenStorage = $tokenStorage;
        $this->translator = $translator;
        $this->rounding = $rounding;
        $this->userCurrencyManager = $userCurrencyManager;
        $this->websiteManager = $websiteManager;
        $this->totalManager = $totalManager;
    }

    /**
     * Creates new shopping list
     *
     * @return ShoppingList
     */
    public function create()
    {
        $shoppingList = new ShoppingList();
        $shoppingList
            ->setOrganization($this->getAccountUser()->getOrganization())
            ->setAccount($this->getAccountUser()->getAccount())
            ->setAccountUser($this->getAccountUser())
            ->setWebsite($this->websiteManager->getCurrentWebsite());

        return $shoppingList;
    }

    /**
     * Creates current shopping list
     *
     * @param string $label
     *
     * @return ShoppingList
     */
    public function createCurrent($label = '')
    {
        $shoppingList = $this->create();
        $shoppingList->setLabel($label !== '' ? $label : $this->translator->trans('oro.shoppinglist.default.label'));

        $this->setCurrent($this->getAccountUser(), $shoppingList);

        return $shoppingList;
    }

    /**
     * @param AccountUser  $accountUser
     * @param ShoppingList $shoppingList
     */
    public function setCurrent(AccountUser $accountUser, ShoppingList $shoppingList)
    {
        $em = $this->managerRegistry->getManagerForClass('OroShoppingListBundle:ShoppingList');
        /** @var ShoppingListRepository $shoppingListRepository */
        $shoppingListRepository = $em->getRepository('OroShoppingListBundle:ShoppingList');
        $currentList = $shoppingListRepository->findCurrentForAccountUser($accountUser);

        if ($currentList instanceof ShoppingList && $currentList->getId() !== $shoppingList->getId()) {
            $currentList->setCurrent(false);
        }
        $shoppingList->setCurrent(true);

        $em->persist($shoppingList);
        $em->flush();
    }

    /**
     * @param LineItem          $lineItem
     * @param ShoppingList|null $shoppingList
     * @param bool|true         $flush
     * @param bool|false        $concatNotes
     */
    public function addLineItem(LineItem $lineItem, ShoppingList $shoppingList, $flush = true, $concatNotes = false)
    {
        $em = $this->managerRegistry->getManagerForClass('OroShoppingListBundle:LineItem');
        $lineItem->setShoppingList($shoppingList);
        /** @var LineItemRepository $repository */
        $repository = $em->getRepository('OroShoppingListBundle:LineItem');
        $duplicate = $repository->findDuplicate($lineItem);
        if ($duplicate instanceof LineItem && $shoppingList->getId()) {
            $quantity = $this->rounding->roundQuantity(
                $duplicate->getQuantity() + $lineItem->getQuantity(),
                $duplicate->getUnit(),
                $duplicate->getProduct()
            );
            $duplicate->setQuantity($quantity);

            if ($concatNotes) {
                $notes = trim(implode(' ', [$duplicate->getNotes(), $lineItem->getNotes()]));
                $duplicate->setNotes($notes);
            }
        } else {
            $shoppingList->addLineItem($lineItem);
            $em->persist($lineItem);
        }

        $this->totalManager->recalculateTotals($shoppingList, false);

        if ($flush) {
            $em->flush();
        }
    }

    /**
     * @param ShoppingList $shoppingList
     * @param Product $product
     * @param bool $flush
     * @return int Number of removed line items
     */
    public function removeProduct(ShoppingList $shoppingList, Product $product, $flush = true)
    {
        $objectManager = $this->managerRegistry->getManagerForClass('OroShoppingListBundle:LineItem');
        $repository = $objectManager->getRepository('OroShoppingListBundle:LineItem');

        $lineItems = $repository->getItemsByShoppingListAndProducts($shoppingList, [$product]);

        foreach ($lineItems as $lineItem) {
            $shoppingList->removeLineItem($lineItem);
            $objectManager->remove($lineItem);
        }

        $this->totalManager->recalculateTotals($shoppingList, false);
        if ($lineItems && $flush) {
            $objectManager->flush();
        }

        return count($lineItems);
    }

    /**
     * @param LineItem $lineItem
     */
    public function removeLineItem(LineItem $lineItem)
    {
        $objectManager = $this->managerRegistry->getManagerForClass('OroShoppingListBundle:LineItem');
        $objectManager->remove($lineItem);
        $shoppingList = $lineItem->getShoppingList();
        $shoppingList->removeLineItem($lineItem);
        $this->totalManager->recalculateTotals($lineItem->getShoppingList(), false);
        $objectManager->flush();
    }

    /**
     * @param array        $lineItems
     * @param ShoppingList $shoppingList
     * @param int          $batchSize
     *
     * @return int
     */
    public function bulkAddLineItems(array $lineItems, ShoppingList $shoppingList, $batchSize)
    {
        $iteration = 0;
        foreach ($lineItems as $iteration => $lineItem) {
            $flush = $iteration % $batchSize === 0 || count($lineItems) === $iteration + 1;
            $this->addLineItem($lineItem, $shoppingList, $flush);
        }

        return $iteration + 1;
    }

    /**
     * @param int $shoppingListId
     *
     * @return ShoppingList
     */
    public function getForCurrentUser($shoppingListId = null)
    {
        $em = $this->managerRegistry->getManagerForClass('OroShoppingListBundle:ShoppingList');
        /** @var ShoppingListRepository $repository */
        $repository = $em->getRepository('OroShoppingListBundle:ShoppingList');
        if ($shoppingListId === null) {
            $shoppingList = $repository->findCurrentForAccountUser($this->getAccountUser());
        } else {
            $shoppingList = $repository->findByUserAndId($this->getAccountUser(), $shoppingListId);
        }

        if (!$shoppingList instanceof ShoppingList) {
            $shoppingList = $this->createCurrent();
        }

        return $shoppingList;
    }

    /**
     * @param bool $create
     * @param string $label
     * @return ShoppingList
     */
    public function getCurrent($create = false, $label = '')
    {
        /* @var $repository ShoppingListRepository */
        $repository = $this->getRepository('OroShoppingListBundle:ShoppingList');
        $shoppingList = $repository->findCurrentForAccountUser($this->getAccountUser());

        if ($create && !$shoppingList instanceof ShoppingList) {
            $label = $this->translator->trans($label ?: 'oro.shoppinglist.default.label');

            $shoppingList = $this->create();
            $shoppingList->setLabel($label);
        }

        return $shoppingList;
    }

    /**
     * @return array
     */
    public function getShoppingLists()
    {
        $accountUser = $this->getAccountUser();
        /* @var $repository ShoppingListRepository */
        $repository = $this->getRepository('OroShoppingListBundle:ShoppingList');

        return $repository->findByUser($accountUser);
    }

    /**
     * @param string $class
     * @return ObjectRepository
     */
    protected function getRepository($class)
    {
        return $this->managerRegistry->getManagerForClass($class)->getRepository($class);
    }

    /**
     * @return string|AccountUser
     */
    protected function getAccountUser()
    {
        if (!$this->accountUser) {
            $token = $this->tokenStorage->getToken();
            if ($token) {
                $this->accountUser = $token->getUser();
            }
        }

        if (!$this->accountUser || !is_object($this->accountUser)) {
            throw new AccessDeniedException();
        }

        return $this->accountUser;
    }
}
