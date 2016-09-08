<?php

namespace Oro\Bundle\ShoppingListBundle\Tests\Functional\Controller\Frontend;

use Oro\Bundle\ShoppingListBundle\Entity\ShoppingListTotal;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Bundle\FrontendTestFrameworkBundle\Migrations\Data\ORM\LoadAccountUserData;
use Oro\Bundle\PricingBundle\DependencyInjection\Configuration;
use Oro\Bundle\PricingBundle\Tests\Functional\DataFixtures\LoadCombinedProductPrices;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Entity\ProductUnit;
use Oro\Bundle\ProductBundle\Tests\Functional\DataFixtures\LoadProductData;
use Oro\Bundle\ShoppingListBundle\Entity\LineItem;
use Oro\Bundle\ShoppingListBundle\Entity\Repository\ShoppingListRepository;
use Oro\Bundle\ShoppingListBundle\Entity\ShoppingList;
use Oro\Bundle\ShoppingListBundle\Tests\Functional\DataFixtures\LoadShoppingLists;

/**
 * @dbIsolation
 */
class AjaxLineItemControllerTest extends WebTestCase
{
    protected function setUp()
    {
        $this->initClient(
            [],
            $this->generateBasicAuthHeader(LoadAccountUserData::AUTH_USER, LoadAccountUserData::AUTH_PW)
        );

        $this->loadFixtures(
            [
                LoadShoppingLists::class,
                LoadCombinedProductPrices::class,
            ]
        );
    }

    /**
     * @dataProvider addProductFromViewDataProvider
     *
     * @param string $product
     * @param string $unit
     * @param int $quantity
     * @param array $expectedSubtotals
     * @param string $shoppingListRef
     */
    public function testAddProductFromView(
        $product,
        $unit,
        $quantity,
        array $expectedSubtotals,
        $shoppingListRef = LoadShoppingLists::SHOPPING_LIST_2
    ) {
        $this->getContainer()->get('oro_config.manager')
            ->set(Configuration::getConfigKeyByName(Configuration::ENABLED_CURRENCIES), ['EUR', 'USD']);
        /** @var Product $product */
        $product = $this->getReference($product);
        /** @var ProductUnit $unit */
        $unit = $this->getReference($unit);

        /** @var ShoppingList $shoppingList */
        $shoppingList = $this->getReference($shoppingListRef);

        $this->client->request(
            'POST',
            $this->getUrl(
                'orob2b_shopping_list_frontend_add_product',
                [
                    'productId' => $product->getId(),
                    'shoppingListId' => $shoppingList->getId(),
                ]
            ),
            [
                'orob2b_product_frontend_line_item' => [
                    'quantity' => $quantity,
                    'unit' => $unit->getCode(),
                    '_token' => $this->getCsrfToken(),
                ],
            ]
        );

        $result = $this->getJsonResponseContent($this->client->getResponse(), 200);

        $this->assertArrayHasKey('successful', $result);
        $this->assertTrue($result['successful']);

        $this->assertArrayHasKey('product', $result);
        $this->assertArrayHasKey('id', $result['product']);
        $this->assertEquals($product->getId(), $result['product']['id']);

        $shoppingList = $this->getContainer()->get('doctrine')
            ->getManagerForClass('OroShoppingListBundle:ShoppingList')
            ->find('OroShoppingListBundle:ShoppingList', $result['shoppingList']['id']);

        $this->assertSubtotals($expectedSubtotals, $shoppingList);
        $this->assertArrayHasKey('shoppingList', $result);
        $this->assertArrayHasKey('id', $result['shoppingList']);
        $this->assertEquals($shoppingList->getId(), $result['shoppingList']['id']);
        $this->assertArrayHasKey('label', $result['shoppingList']);
        $this->assertArrayHasKey('shopping_lists', $result['product']);

        $this->assertArrayHasKey('shopping_list_id', $result['product']['shopping_lists'][0]);
        $this->assertArrayHasKey('shopping_list_label', $result['product']['shopping_lists'][0]);
        $this->assertArrayHasKey('is_current', $result['product']['shopping_lists'][0]);
        $this->assertArrayHasKey('line_items', $result['product']['shopping_lists'][0]);
        $this->assertArrayHasKey('unit', $result['product']['shopping_lists'][0]['line_items'][0]);
        $this->assertArrayHasKey('quantity', $result['product']['shopping_lists'][0]['line_items'][0]);
        $this->assertArrayHasKey('line_item_id', $result['product']['shopping_lists'][0]['line_items'][0]);

    }

    /**
     * @return array
     */
    public function addProductFromViewDataProvider()
    {
        return [
            [
                'product' => LoadProductData::PRODUCT_1,
                'unit' => 'product_unit.bottle',
                'quantity' => 110,
                'expectedSubtotals' => ['EUR' => 1342, 'USD' => 1441],
            ],
            [
                'product' => LoadProductData::PRODUCT_2,
                'unit' => 'product_unit.liter',
                'quantity' => 14,
                'expectedSubtotals' => ['EUR' => 1573, 'USD' => 1611.8],
            ],
            [
                'product' => LoadProductData::PRODUCT_1,
                'unit' => 'product_unit.bottle',
                'quantity' => 10,
                'expectedSubtotals' => ['EUR' => 122, 'USD' => 131],
                'shoppingListRef' => LoadShoppingLists::SHOPPING_LIST_1,
            ],
        ];
    }

    public function testAddProductFromViewNotValidData()
    {
        /** @var Product $product */
        $product = $this->getReference('product.1');

        $this->client->request(
            'POST',
            $this->getUrl('orob2b_shopping_list_frontend_add_product', ['productId' => $product->getId()]),
            [
                'orob2b_product_frontend_line_item' => [
                    'quantity' => null,
                    'unit' => null,
                    '_token' => $this->getCsrfToken(),
                ],
            ]
        );

        $result = $this->getJsonResponseContent($this->client->getResponse(), 200);

        $this->assertArrayHasKey('successful', $result);
        $this->assertFalse($result['successful']);
    }

    /**
     * @depends      testAddProductFromView
     * @dataProvider removeProductFromViewProvider
     *
     * @param string $productRef
     * @param bool $expectedResult
     * @param string $expectedMessage
     * @param int $expectedInitCount
     * @param bool $removeCurrent
     * @param string $shoppingListRef
     */
    public function testRemoveProductFromView(
        $productRef,
        $expectedResult,
        $expectedMessage,
        $expectedInitCount,
        $removeCurrent = false,
        $shoppingListRef = LoadShoppingLists::SHOPPING_LIST_2
    ) {
        /** @var ShoppingList $shoppingList */
        $shoppingList = $this->getReference($shoppingListRef);
        $shoppingList = $this->getShoppingList($shoppingList->getId());

        $this->assertCount($expectedInitCount, $shoppingList->getLineItems());

        /** @var Product $product */
        $product = $this->getReference($productRef);

        if ($removeCurrent) {
            $this->setShoppingListCurrent($shoppingList, false);
        }

        $this->client->request(
            'POST',
            $this->getUrl(
                'orob2b_shopping_list_frontend_remove_product',
                [
                    'productId' => $product->getId(),
                    'shoppingListId' => $shoppingList->getId(),
                ]
            )
        );

        $result = $this->getJsonResponseContent($this->client->getResponse(), 200);

        $this->assertArrayHasKey('successful', $result);
        $this->assertEquals($expectedResult, $result['successful']);

        $this->assertArrayHasKey('message', $result);
        $this->assertEquals(sprintf($expectedMessage, $shoppingList->getId()), $result['message']);

        $shoppingList = $this->getShoppingList($shoppingList->getId());

        if ($expectedResult) {
            $this->assertCount($expectedInitCount - 1, $shoppingList->getLineItems());

            /** @var ShoppingListTotal[] $totals */
            $totals = $this->getContainer()->get('doctrine')
                ->getRepository(ShoppingListTotal::class)
                ->findBy(['shoppingList' => $shoppingList]);
            $subtotalProvider = $this->getContainer()
                ->get('orob2b_pricing.subtotal_processor.provider.subtotal_line_item_not_priced');
            foreach ($totals as $total) {
                $expectedSubtotal = $subtotalProvider
                    ->getSubtotalByCurrency($shoppingList, $total->getCurrency())
                    ->getAmount();
                $actualSubtotal = $total->getSubtotal()->getAmount();
                $this->assertEquals($expectedSubtotal, $actualSubtotal);
            }
        } else {
            $this->assertCount($expectedInitCount, $shoppingList->getLineItems());
        }

        if ($removeCurrent) {
            $this->setShoppingListCurrent($shoppingList, true);
        }
    }

    /**
     * @param $id
     * @return null|ShoppingList
     */
    protected function getShoppingList($id)
    {
        return $this->getShoppingListRepository()->find($id);
    }

    /**
     * @param ShoppingList $currentShoppingList
     * @param bool $isCurrent
     */
    protected function setShoppingListCurrent(ShoppingList $currentShoppingList, $isCurrent)
    {
        $container = $this->getContainer();
        $manager = $container->get('doctrine')->getManagerForClass(
            $container->getParameter('orob2b_shopping_list.entity.shopping_list.class')
        );

        /** @var ShoppingList[] $shoppingLists */
        $shoppingLists = $this->getShoppingListRepository()->findAll();
        foreach ($shoppingLists as $shoppingList) {
            $shoppingList->setCurrent(false);

            $manager->persist($shoppingList);
        }

        $currentShoppingList->setCurrent($isCurrent);

        $manager->persist($currentShoppingList);
        $manager->flush();
    }

    /**
     * @return array
     */
    public function removeProductFromViewProvider()
    {
        return [
            [
                'productRef' => LoadProductData::PRODUCT_1,
                'expectedResult' => true,
                'expectedMessage' => 'Product has been removed from "<a href="/account/shoppinglist/%s">' .
                    'shopping_list_2_label</a>"',
                'expectedInitCount' => 2,
            ],
            [
                'productRef' => LoadProductData::PRODUCT_2,
                'expectedResult' => true,
                'expectedMessage' => 'Product has been removed from "<a href="/account/shoppinglist/%s">' .
                    'shopping_list_2_label</a>"',
                'expectedInitCount' => 1,
            ],
            [
                'productRef' => LoadProductData::PRODUCT_1,
                'expectedResult' => false,
                'expectedMessage' => 'No current ShoppingList or no Product in current ShoppingList',
                'expectedInitCount' => 0,
            ],
            [
                'productRef' => LoadProductData::PRODUCT_1,
                'expectedResult' => false,
                'expectedMessage' => 'No current ShoppingList or no Product in current ShoppingList',
                'expectedInitCount' => 0,
                'removeCurrent' => true,
                'shoppingListRef' => LoadShoppingLists::SHOPPING_LIST_2,
            ],
            [
                'productRef' => LoadProductData::PRODUCT_1,
                'expectedResult' => true,
                'expectedMessage' => 'Product has been removed from "<a href="/account/shoppinglist/%s">' .
                    'shopping_list_1_label</a>"',
                'expectedInitCount' => 1,
                'removeCurrent' => false,
                'shoppingListRef' => LoadShoppingLists::SHOPPING_LIST_1,
            ],
        ];
    }

    public function testAddProductsMassAction()
    {
        /** @var ShoppingList $shoppingList */
        $shoppingList = $this->getReference(LoadShoppingLists::SHOPPING_LIST_3);

        $this->client->request(
            'GET',
            $this->getUrl(
                'orob2b_shopping_list_add_products_massaction',
                [
                    'gridName' => 'frontend-products-grid',
                    'actionName' => 'orob2b_shoppinglist_frontend_addlineitemlist' . $shoppingList->getId(),
                    'shoppingList' => $shoppingList->getId(),
                    'inset' => 1,
                    'values' => $this->getReference('product.1')->getId(),
                ]
            )
        );

        $result = $this->getJsonResponseContent($this->client->getResponse(), 200);

        $this->assertArrayHasKey('successful', $result);
        $this->assertTrue($result['successful']);
        $this->assertArrayHasKey('count', $result);
        $this->assertEquals(1, $result['count']);
    }

    public function testAddProductsToNewMassAction()
    {
        /** @var Product $product */
        $product = $this->getReference(LoadProductData::PRODUCT_1);

        $shoppingListsCount = count($this->getShoppingListRepository()->findAll());

        $crawler = $this->client->request(
            'GET',
            $this->getUrl(
                'orob2b_shopping_list_add_products_to_new_massaction',
                [
                    'gridName' => 'frontend-products-grid',
                    'actionName' => 'orob2b_shoppinglist_frontend_addlineitemnew',
                    '_widgetContainer' => 'dialog',
                    '_wid' => 'test-uuid',
                ]
            )
        );

        $this->assertHtmlResponseStatusCodeEquals($this->client->getResponse(), 200);

        $form = $crawler->selectButton('Create and Add')->form();
        $form['orob2b_shopping_list_type[label]'] = 'TestShoppingList';

        $this->client->request(
            $form->getMethod(),
            $this->getUrl(
                'orob2b_shopping_list_add_products_to_new_massaction',
                [
                    'gridName' => 'frontend-products-grid',
                    'actionName' => 'orob2b_shoppinglist_frontend_addlineitemnew',
                    'inset' => 1,
                    'values' => $product->getId(),
                    '_widgetContainer' => 'dialog',
                    '_wid' => 'test-uuid',
                ]
            ),
            $form->getPhpValues()
        );

        $this->assertHtmlResponseStatusCodeEquals($this->client->getResponse(), 200);

        $shoppingLists = $this->getShoppingListRepository()->findBy([], ['id' => 'DESC']);

        $this->assertCount($shoppingListsCount + 1, $shoppingLists);

        /** @var ShoppingList $shoppingList */
        $shoppingList = reset($shoppingLists);
        $lineItems = $shoppingList->getLineItems();

        $this->assertCount(1, $lineItems);

        /** @var LineItem $lineItem */
        $lineItem = $lineItems->first();
        $this->assertEquals($product->getId(), $lineItem->getProduct()->getId());
    }

    /**
     * @return string
     */
    protected function getCsrfToken()
    {
        return $this->client
            ->getContainer()
            ->get('security.csrf.token_manager')
            ->getToken('orob2b_product_frontend_line_item')
            ->getValue();
    }

    /**
     * @return ShoppingListRepository
     */
    protected function getShoppingListRepository()
    {
        return $this->getContainer()->get('doctrine')->getRepository('OroShoppingListBundle:ShoppingList');
    }

    /**
     * @param array $expectedSubtotals
     * @param ShoppingList $shoppingList
     */
    protected function assertSubtotals(array $expectedSubtotals, ShoppingList $shoppingList)
    {
        foreach ($expectedSubtotals as $currency => $value) {
            foreach ($shoppingList->getTotals() as $total) {
                if ($total->getCurrency() === $currency) {
                    $this->assertEquals($value, $total->getSubtotal()->getAmount());
                }
            }
        }
    }
}