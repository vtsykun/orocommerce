<?php

namespace Oro\Bundle\ShoppingListBundle\Tests\Functional\DataFixtures;

use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

use Oro\Bundle\FrontendTestFrameworkBundle\Migrations\Data\ORM\LoadAccountUserData;
use Oro\Bundle\CustomerBundle\Entity\AccountUser;
use Oro\Bundle\ShoppingListBundle\Entity\ShoppingList;
use Oro\Bundle\WebsiteBundle\Entity\Website;
use Oro\Bundle\WebsiteBundle\Tests\Functional\DataFixtures\LoadWebsiteData;

class LoadShoppingLists extends AbstractFixture implements DependentFixtureInterface
{
    const SHOPPING_LIST_1 = 'shopping_list_1';
    const SHOPPING_LIST_2 = 'shopping_list_2';
    const SHOPPING_LIST_3 = 'shopping_list_3';
    const SHOPPING_LIST_4 = 'shopping_list_4';
    const SHOPPING_LIST_5 = 'shopping_list_5';

    /**
     * @var array
     */
    protected $shoppingListsWithDefaultWebsite = [
        self::SHOPPING_LIST_1,
        self::SHOPPING_LIST_2
    ];

    /**
     * {@inheritdoc}
     */
    public function getDependencies()
    {
        return [
            LoadWebsiteData::class
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function load(ObjectManager $manager)
    {
        $accountUser = $this->getAccountUser($manager);
        $lists = $this->getData();
        foreach ($lists as $listLabel) {
            $isCurrent = $listLabel === self::SHOPPING_LIST_2;
            $this->createShoppingList(
                $manager,
                $accountUser,
                $listLabel,
                $isCurrent
            );
        }

        $manager->flush();
    }

    /**
     * @param ObjectManager $manager
     * @param AccountUser $accountUser
     * @param string $name
     * @param bool $isCurrent
     * @return ShoppingList
     */
    protected function createShoppingList(
        ObjectManager $manager,
        AccountUser $accountUser,
        $name,
        $isCurrent = false
    ) {
        $shoppingList = new ShoppingList();
        $shoppingList->setOrganization($accountUser->getOrganization());
        $shoppingList->setAccountUser($accountUser);
        $shoppingList->setAccount($accountUser->getAccount());
        $shoppingList->setLabel($name . '_label');
        $shoppingList->setNotes($name . '_notes');
        $shoppingList->setCurrent($isCurrent);
        if (in_array($name, $this->shoppingListsWithDefaultWebsite, true)) {
            $shoppingList->setWebsite($this->getDefaultWebsite($manager));
        } else {
            $shoppingList->setWebsite($this->getReference(LoadWebsiteData::WEBSITE1));
        }
        $manager->persist($shoppingList);
        $this->addReference($name, $shoppingList);

        return $shoppingList;
    }

    /**
     * @param ObjectManager $manager
     *
     * @return AccountUser
     * @throws \LogicException
     */
    protected function getAccountUser(ObjectManager $manager)
    {
        $accountUser = $manager->getRepository('OroCustomerBundle:AccountUser')
            ->findOneBy(['username' => LoadAccountUserData::AUTH_USER]);

        if (!$accountUser) {
            throw new \LogicException('Test account user not loaded');
        }

        return $accountUser;
    }

    /**
     * @return array
     */
    protected function getData()
    {
        return [
            self::SHOPPING_LIST_1,
            self::SHOPPING_LIST_2,
            self::SHOPPING_LIST_3,
            self::SHOPPING_LIST_4,
            self::SHOPPING_LIST_5
        ];
    }

    /**
     * @param ObjectManager $manager
     * @return Website
     */
    protected function getDefaultWebsite(ObjectManager $manager)
    {
        return $manager->getRepository(Website::class)->getDefaultWebsite();
    }
}
