<?php

namespace Oro\Bundle\PricingBundle\Tests\Functional\Entity\Repository;

use Doctrine\Common\Collections\ArrayCollection;

use Oro\Bundle\CurrencyBundle\Entity\Price;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Bundle\PricingBundle\Entity\BaseProductPrice;
use Oro\Bundle\PricingBundle\Entity\PriceList;
use Oro\Bundle\PricingBundle\Entity\PriceListToProduct;
use Oro\Bundle\PricingBundle\Entity\PriceRule;
use Oro\Bundle\PricingBundle\Entity\ProductPrice;
use Oro\Bundle\PricingBundle\Entity\Repository\ProductPriceRepository;
use Oro\Bundle\PricingBundle\Tests\Functional\DataFixtures\LoadPriceLists;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Entity\ProductUnit;
use Oro\Bundle\ProductBundle\Tests\Functional\DataFixtures\LoadProductData;
use Oro\Bundle\PricingBundle\Tests\Functional\DataFixtures\LoadProductPrices;

/**
 * @dbIsolation
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class ProductPriceRepositoryTest extends WebTestCase
{
    /**
     * @var ProductPriceRepository
     */
    protected $repository;

    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        $this->initClient();

        $this->loadFixtures(
            [
                LoadProductPrices::class,
            ]
        );

        $this->repository = $this->getContainer()->get('doctrine')
            ->getRepository('OroPricingBundle:ProductPrice');
    }

    /**
     * @dataProvider unitDataProvider
     * @param string $priceList
     * @param string $product
     * @param null|string $currency
     * @param array $expected
     */
    public function testGetProductUnitsByPriceList($priceList, $product, $currency = null, array $expected = [])
    {
        /** @var PriceList $priceList */
        $priceList = $this->getReference($priceList);
        /** @var Product $product */
        $product = $this->getReference($product);

        $units = $this->repository->getProductUnitsByPriceList($priceList, $product, $currency);
        $this->assertCount(count($expected), $units);
        foreach ($units as $unit) {
            $this->assertContains($unit->getCode(), $expected);
        }
    }

    /**
     * @return array
     */
    public function unitDataProvider()
    {
        return [
            [
                LoadPriceLists::PRICE_LIST_1,
                'product.1',
                null,
                ['liter', 'bottle']
            ],
            [
                LoadPriceLists::PRICE_LIST_1,
                'product.1',
                'EUR',
                ['bottle']
            ]
        ];
    }

    /**
     * @dataProvider getProductsUnitsByPriceListDataProvider
     * @param string $priceList
     * @param array $products
     * @param null|string $currency
     * @param array $expected
     */
    public function testGetProductsUnitsByPriceList($priceList, array $products, $currency = null, array $expected = [])
    {
        /** @var PriceList $priceList */
        $priceList = $this->getReference($priceList);

        $productsCollection = new ArrayCollection();

        foreach ($products as $productName) {
            /** @var Product $product */
            $product = $this->getReference($productName);
            $productsCollection->add($product);
        }

        $actual = $this->repository->getProductsUnitsByPriceList($priceList, $productsCollection, $currency);

        $expectedData = [];
        foreach ($expected as $productName => $units) {
            $product = $this->getReference($productName);
            $expectedData[$product->getId()] = $units;
        }

        $this->assertEquals($expectedData, $actual);
    }

    /**
     * @return array
     */
    public function getProductsUnitsByPriceListDataProvider()
    {
        return [
            [
                'priceList' => LoadPriceLists::PRICE_LIST_1,
                'products' => [
                    'product.1',
                    'product.2',
                    'product.3'
                ],
                'currency' => 'USD',
                'expected' => [
                    'product.1' => ['liter'],
                    'product.2' => ['liter'],
                    'product.3' => ['liter'],
                ]
            ],
            [
                'priceList' => LoadPriceLists::PRICE_LIST_1,
                'products' => [
                    'product.1',
                    'product.2',
                    'product.3'
                ],
                'currency' => 'EUR',
                'expected' => [
                    'product.1' => ['bottle'],
                    'product.2' => ['liter']
                ]
            ]
        ];
    }

    /**
     * @param string $productReference
     * @param array $priceReferences
     * @dataProvider getPricesByProductDataProvider
     */
    public function testGetPricesByProduct($productReference, array $priceReferences)
    {
        /** @var Product $product */
        $product = $this->getReference($productReference);

        $expectedPrices = [];
        foreach ($priceReferences as $priceReference) {
            $expectedPrices[] = $this->getReference($priceReference);
        }

        $this->assertEquals(
            $this->getPriceIds($expectedPrices),
            $this->getPriceIds($this->repository->getPricesByProduct($product))
        );
    }

    /**
     * @return array
     */
    public function getPricesByProductDataProvider()
    {
        return [
            'first product' => [
                'productReference' => 'product.1',
                'priceReferences' => [
                    'product_price.10',
                    'product_price.2',
                    'product_price.7',
                    'product_price.1',
                    'product_price.6',
                ],
            ],
            'second product' => [
                'productReference' => 'product.2',
                'priceReferences' => [
                    'product_price.13',
                    'product_price.11',
                    'product_price.8',
                    'product_price.3',
                    'product_price.12',
                    'product_price.5',
                    'product_price.4',
                    'product_price.16',
                    'product_price.15',
                ],
            ],
        ];
    }

    /**
     * @param string|null $priceList
     * @param array $products
     * @param array $expectedPrices
     * @param bool $getTierPrices
     * @param string $currency
     * @param array $orderBy
     *
     * @dataProvider findByPriceListIdAndProductIdsDataProvider
     */
    public function testFindByPriceListIdAndProductIds(
        $priceList,
        array $products,
        array $expectedPrices,
        $getTierPrices = true,
        $currency = null,
        array $orderBy = ['unit' => 'ASC', 'quantity' => 'ASC']
    ) {
        $priceListId = -1;
        if ($priceList) {
            /** @var PriceList $priceListEntity */
            $priceListEntity = $this->getReference($priceList);
            $priceListId = $priceListEntity->getId();
        }

        $productIds = [];
        foreach ($products as $product) {
            /** @var Product $productEntity */
            $productEntity = $this->getReference($product);
            $productIds[] = $productEntity->getId();
        }

        $expectedPriceIds = [];
        foreach ($expectedPrices as $price) {
            /** @var ProductPrice $priceEntity */
            $priceEntity = $this->getReference($price);
            $expectedPriceIds[] = $priceEntity->getId();
        }

        $actualPrices = $this->repository->findByPriceListIdAndProductIds(
            $priceListId,
            $productIds,
            $getTierPrices,
            $currency,
            null,
            $orderBy
        );

        $actualPriceIds = $this->getPriceIds($actualPrices);

        $this->assertEquals($expectedPriceIds, $actualPriceIds);
    }

    /**
     * @return array
     */
    public function findByPriceListIdAndProductIdsDataProvider()
    {
        return [
            'empty products' => [
                'priceList' => LoadPriceLists::PRICE_LIST_1,
                'products' => [],
                'expectedPrices' => [],
            ],
            'empty products without tier prices' => [
                'priceList' => LoadPriceLists::PRICE_LIST_1,
                'products' => [],
                'expectedPrices' => [],
            ],
            'not existing price list' => [
                'priceList' => null,
                'products' => ['product.1'],
                'expectedPrices' => [],
            ],
            'not existing price list without tier prices' => [
                'priceList' => null,
                'products' => ['product.1'],
                'expectedPrices' => [],
            ],
            'first valid set' => [
                'priceList' => LoadPriceLists::PRICE_LIST_1,
                'products' => ['product.1'],
                'expectedPrices' => ['product_price.10', 'product_price.2', 'product_price.7', 'product_price.1'],
            ],
            'first valid set without tier prices' => [
                'priceList' => LoadPriceLists::PRICE_LIST_1,
                'products' => ['product.1'],
                'expectedPrices' => ['product_price.10', 'product_price.7'],
                'getTierPrices' => false
            ],
            'first valid set without tier prices with currency' => [
                'priceList' => LoadPriceLists::PRICE_LIST_1,
                'products' => ['product.1'],
                'expectedPrices' => ['product_price.10'],
                'getTierPrices' => false,
                'currency' => 'EUR'
            ],
            'second valid set' => [
                'priceList' => LoadPriceLists::PRICE_LIST_2,
                'products' => ['product.1', 'product.2'],
                'expectedPrices' => ['product_price.5', 'product_price.12', 'product_price.4', 'product_price.6'],
            ],
            'second valid set without tier prices' => [
                'priceList' => LoadPriceLists::PRICE_LIST_2,
                'products' => ['product.1', 'product.2'],
                'expectedPrices' => [],
                'getTierPrices' => false
            ],
            'second valid set with currency' => [
                'priceList' => LoadPriceLists::PRICE_LIST_2,
                'products' => ['product.1', 'product.2'],
                'expectedPrices' => ['product_price.5', 'product_price.4', 'product_price.6'],
                'getTierPrices' => true,
                'currency' => 'USD'
            ],
            'first valid set with order by currency, unit and quantity' => [
                'priceList' => LoadPriceLists::PRICE_LIST_2,
                'products' => ['product.1', 'product.2'],
                'expectedPrices' => ['product_price.5', 'product_price.4', 'product_price.6', 'product_price.12'],
                'getTierPrices' => true,
                'currency' => null,
                ['currency' => 'DESC', 'unit' => 'ASC', 'quantity' => 'ASC']
            ],
        ];
    }

    /**
     * @dataProvider getPricesBatchDataProvider
     *
     * @param string $priceList
     * @param array $products
     * @param array $productUnits
     * @param array $expectedPrices
     * @param array $currencies
     */
    public function testGetPricesBatch(
        $priceList,
        array $products,
        array $productUnits,
        array $expectedPrices,
        array $currencies = []
    ) {
        /** @var PriceList $priceListEntity */
        $priceListEntity = $this->getReference($priceList);
        $priceListId = $priceListEntity->getId();

        $productIds = [];
        foreach ($products as $product) {
            /** @var Product $productEntity */
            $productEntity = $this->getReference($product);
            $productIds[] = $productEntity->getId();
        }

        $productUnitCodes = [];
        foreach ($productUnits as $productUnit) {
            /** @var ProductUnit $productUnit */
            $productUnitEntity = $this->getReference($productUnit);
            $productUnitCodes[] = $productUnitEntity->getCode();
        }

        $expectedPriceData = [];
        foreach ($expectedPrices as $price) {
            /** @var ProductPrice $priceEntity */
            $priceEntity = $this->getReference($price);
            $expectedPriceData[] = [
                'id' => $priceEntity->getProduct()->getId(),
                'code' => $priceEntity->getUnit()->getCode(),
                'quantity' => $priceEntity->getQuantity(),
                'value' => $priceEntity->getPrice()->getValue(),
                'currency' => $priceEntity->getPrice()->getCurrency(),
            ];
        }
        $sorter = function ($a, $b) {
            if ($a['id'] === $b['id']) {
                return 0;
            }
            return ($a['id'] < $b['id']) ? -1 : 1;
        };

        $actualPrices = $this->repository->getPricesBatch($priceListId, $productIds, $productUnitCodes, $currencies);

        $expectedPriceData = usort($expectedPriceData, $sorter);
        $actualPrices = usort($actualPrices, $sorter);

        $this->assertEquals($expectedPriceData, $actualPrices);
    }

    /**
     * @return array
     */
    public function getPricesBatchDataProvider()
    {
        return [
            'empty' => [
                'priceList' => LoadPriceLists::PRICE_LIST_1,
                'products' => [],
                'productUnits' => [],
                'expectedPrices' => [],
            ],
            'first valid set' => [
                'priceList' => LoadPriceLists::PRICE_LIST_1,
                'products' => ['product.1', 'product.2'],
                'productUnits' => ['product_unit.liter'],
                'expectedPrices' => [
                    'product_price.7',
                    'product_price.8',
                    'product_price.1',
                    'product_price.3',
                    'product_price.11'
                ],
            ],
            'first valid set with currency' => [
                'priceList' => LoadPriceLists::PRICE_LIST_1,
                'products' => ['product.1', 'product.2'],
                'productUnits' => ['product_unit.liter'],
                'expectedPrices' => ['product_price.11'],
                'currencies' => ['EUR']
            ],
            'second valid set' => [
                'priceList' => LoadPriceLists::PRICE_LIST_2,
                'products' => ['product.2'],
                'productUnits' => ['product_unit.bottle'],
                'expectedPrices' => ['product_price.5', 'product_price.12'],
            ],
            'second valid set with currency' => [
                'priceList' => LoadPriceLists::PRICE_LIST_2,
                'products' => ['product.2'],
                'productUnits' => ['product_unit.bottle'],
                'expectedPrices' => ['product_price.5'],
                'currencies' => ['USD']
            ],
        ];
    }

    public function testDeleteByProductUnit()
    {
        /** @var Product $product */
        $product = $this->getReference('product.1');
        /** @var Product $notRemovedProduct */
        $notRemovedProduct = $this->getReference('product.2');
        /** @var ProductUnit $unit */
        $unit = $this->getReference('product_unit.liter');
        /** @var ProductUnit $unit */
        $notRemovedUnit = $this->getReference('product_unit.bottle');

        $this->repository->deleteByProductUnit($product, $unit);

        $this->assertEmpty(
            $this->repository->findBy(
                [
                    'product' => $product,
                    'unit' => $unit
                ]
            )
        );

        $this->assertNotEmpty(
            $this->repository->findBy(
                [
                    'product' => $notRemovedProduct,
                    'unit' => $unit
                ]
            )
        );

        $this->assertNotEmpty(
            $this->repository->findBy(
                [
                    'product' => $product,
                    'unit' => $notRemovedUnit
                ]
            )
        );
    }

    public function testGetAvailableCurrencies()
    {
        $this->assertEquals(
            ['EUR' => 'EUR', 'USD' => 'USD'],
            $this->repository->getAvailableCurrencies()
        );

        $em = $this->getContainer()->get('doctrine')->getManager();

        $price = new Price();
        $price->setValue(1);
        $price->setCurrency('UAH');

        /** @var Product $product */
        $product = $this->getReference('product.1');

        /** @var ProductUnit $unit */
        $unit = $this->getReference('product_unit.liter');

        /** @var PriceList $priceList */
        $priceList = $this->getReference(LoadPriceLists::PRICE_LIST_1);

        $productPrice = new ProductPrice();
        $productPrice
            ->setPrice($price)
            ->setProduct($product)
            ->setQuantity(1)
            ->setUnit($unit)
            ->setPriceList($priceList);

        $em->persist($productPrice);
        $em->flush();

        $this->assertEquals(
            ['EUR' => 'EUR', 'UAH' => 'UAH', 'USD' => 'USD'],
            $this->repository->getAvailableCurrencies()
        );
    }

    public function testCountByPriceList()
    {
        /** @var PriceList $priceList */
        $priceList = $this->getReference(LoadPriceLists::PRICE_LIST_1);

        $this->assertCount(
            $this->repository->countByPriceList($priceList),
            $this->repository->findBy(['priceList' => $priceList->getId()])
        );
    }

    public function testDeleteByPriceList()
    {
        /** @var PriceList $priceList */
        $priceList = $this->getReference(LoadPriceLists::PRICE_LIST_1);

        $this->repository->deleteByPriceList($priceList);

        $this->assertEmpty($this->repository->findBy(['priceList' => $priceList->getId()]));

        /** @var PriceList $priceList2 */
        $priceList2 = $this->getReference(LoadPriceLists::PRICE_LIST_2);
        $this->assertNotEmpty($this->repository->findBy(['priceList' => $priceList2->getId()]));

        $this->repository->deleteByPriceList($priceList2);
        $this->assertEmpty($this->repository->findBy(['priceList' => $priceList2->getId()]));
    }

    public function testCopyPrices()
    {
        /** @var PriceList $priceList */
        $priceList = $this->getReference(LoadPriceLists::PRICE_LIST_1);
        $newPriceList = new PriceList();
        $newPriceList->setName('test');

        $em = $this->getContainer()->get('doctrine')->getManagerForClass('OroPricingBundle:ProductPrice');
        $em->persist($newPriceList);
        $em->flush();

        $priceRepository = $em->getRepository('OroPricingBundle:ProductPrice');
        $priceRepository->copyPrices(
            $priceList,
            $newPriceList,
            $this->getContainer()->get('oro_entity.orm.insert_from_select_query_executor')
        );

        $sourcePrices = $priceRepository->findBy(
            ['priceList' => $priceList],
            ['product' => 'ASC', 'quantity' => 'ASC', 'value' => 'ASC']
        );

        $targetPrices = $priceRepository->findBy(
            ['priceList' => $newPriceList],
            ['product' => 'ASC', 'quantity' => 'ASC', 'value' => 'ASC']
        );

        $priceToArrayCallback = function (BaseProductPrice $price) {
            return [
                'product' => $price->getProduct()->getId(),
                'productSku' => $price->getProductSku(),
                'quantity' => $price->getQuantity(),
                'unit' => $price->getProductUnitCode(),
                'value' => $price->getPrice()->getValue(),
                'currency' => $price->getPrice()->getCurrency(),
            ];
        };

        $sourcePricesArray = array_map($priceToArrayCallback, $sourcePrices);
        $targetPricesArray = array_map($priceToArrayCallback, $targetPrices);

        $this->assertSame($sourcePricesArray, $targetPricesArray);
    }

    public function testDeleteGeneratedPrices()
    {
        $registry = $this->getContainer()->get('doctrine');
        $manager = $registry->getManagerForClass(PriceRule::class);

        /** @var PriceList $priceList */
        $priceList = $this->getReference(LoadPriceLists::PRICE_LIST_1);
        /** @var ProductPriceRepository $repository */
        $repository = $manager->getRepository('OroPricingBundle:ProductPrice');
        $manualPrices = $repository->findBy(['priceList' => $priceList, 'priceRule' => null]);

        $rule = $this->createPriceListRule($priceList);
        $productPrice = $this->createProductPrice($priceList, $rule);

        $manager->persist($rule);
        $manager->persist($productPrice);
        $manager->flush();

        $repository->deleteGeneratedPrices($priceList, $productPrice->getProduct());

        $actual = $repository->findBy(['priceList' => $priceList]);
        $this->assertEquals($manualPrices, $actual);
    }

    public function testDeleteGeneratedPricesByRule()
    {
        $registry = $this->getContainer()->get('doctrine');
        $manager = $registry->getManagerForClass(PriceRule::class);

        /** @var PriceList $priceList */
        $priceList = $this->getReference(LoadPriceLists::PRICE_LIST_1);
        /** @var ProductPriceRepository $repository */
        $repository = $manager->getRepository('OroPricingBundle:ProductPrice');

        $pricesCount = $this->getPricesCount();

        $rule1 = $this->createPriceListRule($priceList);
        $rule2 = $this->createPriceListRule($priceList);
        $productPrice1 = $this->createProductPrice($priceList, $rule1);
        $productPrice2 = $this->createProductPrice($priceList, $rule2, 'EUR');

        $manager->persist($rule1);
        $manager->persist($rule2);
        $manager->persist($productPrice1);
        $manager->persist($productPrice2);
        $manager->flush();

        $repository->deleteGeneratedPricesByRule($rule1);
        $this->assertEmpty($repository->findBy(['priceRule'=>$rule1]));
        $this->assertCount(1, $repository->findBy(['priceRule'=>$rule2]));
        $this->assertEquals($pricesCount + 1, $this->getPricesCount());
    }

    public function testDeleteInvalidPrices()
    {
        $objectRepository = $this->getContainer()->get('doctrine')
            ->getRepository(PriceListToProduct::class);

        $priceList = $this->getReference(LoadPriceLists::PRICE_LIST_1);
        $product = $this->getReference(LoadProductData::PRODUCT_2);

        $objectRepository
            ->createQueryBuilder('productRelation')
            ->delete(PriceListToProduct::class, 'productRelation')
            ->where('productRelation.priceList = :priceList AND productRelation.product = :product')
            ->setParameter('priceList', $priceList)
            ->setParameter('product', $product)
            ->getQuery()
            ->execute();

        $prices = $this->repository->findBy(['priceList' => $priceList]);
        $this->assertNotEmpty($prices);

        $this->repository->deleteInvalidPrices($priceList);

        /** @var ProductPrice $price */
        $price = $this->repository->findOneBy(['priceList' => $priceList]);
        $this->assertEquals($price->getProduct()->getId(), $this->getReference(LoadProductData::PRODUCT_1)->getId());
    }

    /**
     * @param ProductPrice[] $prices
     * @return array
     */
    protected function getPriceIds(array $prices)
    {
        $priceIds = [];
        foreach ($prices as $price) {
            $priceIds[] = $price->getId();
        }

        return $priceIds;
    }

    /**
     * @return int
     */
    protected function getPricesCount()
    {
        $repository = $this->getContainer()
            ->get('doctrine')
            ->getRepository('OroPricingBundle:ProductPrice');
        return (int)$repository->createQueryBuilder('pp')
            ->select('count(pp.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param PriceList $priceList
     * @return PriceRule
     */
    protected function createPriceListRule(PriceList $priceList)
    {
        $rule = new PriceRule();
        $rule->setRule('10')
            ->setPriority(1)
            ->setQuantity(1)
            ->setPriceList($priceList)
            ->setCurrency('USD');

        return $rule;
    }

    /**
     * @param PriceList $priceList
     * @param PriceRule $rule
     * @param string $currency
     * @return ProductPrice
     */
    protected function createProductPrice(PriceList $priceList, PriceRule $rule, $currency = 'USD')
    {
        /** @var ProductUnit $unit */
        $unit = $this->getReference('product_unit.box');
        /** @var Product $product */
        $product = $this->getReference(LoadProductData::PRODUCT_1);

        $productPrice = new ProductPrice();
        $productPrice->setPriceList($priceList)
            ->setPrice(Price::create(1, $currency))
            ->setQuantity(1)
            ->setPriceRule($rule)
            ->setUnit($unit)
            ->setProduct($product);

        return $productPrice;
    }
}
