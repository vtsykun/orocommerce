<?php

namespace Oro\Bundle\ProductBundle\Tests\Functional\Controller\Frontend;

use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Bundle\FrontendTestFrameworkBundle\Migrations\Data\ORM\LoadAccountUserData;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Entity\ProductUnit;

/**
 * @dbIsolation
 */
class AjaxProductUnitControllerTest extends WebTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->initClient(
            [],
            $this->generateBasicAuthHeader(LoadAccountUserData::AUTH_USER, LoadAccountUserData::AUTH_PW)
        );

        $this->loadFixtures(['Oro\Bundle\ProductBundle\Tests\Functional\DataFixtures\LoadProductUnitPrecisions']);
    }

    /**
     * @param string $productReference
     * @param array $expectedData
     * @param bool $isShort
     *
     * @dataProvider productUnitsDataProvider
     */
    public function testProductUnitsAction($productReference, array $expectedData, $isShort = false)
    {
        $product = $this->getProduct($productReference);

        $this->client->request(
            'GET',
            $this->getUrl('oro_product_frontend_ajaxproductunit_productunits', [
                'id' => $product->getId(),
                'short' => $isShort
            ])
        );

        $result = $this->client->getResponse();
        static::assertJsonResponseStatusCodeEquals($result, 200);

        $data = json_decode($result->getContent(), true);

        static::assertArrayHasKey('units', $data);
        static::assertEquals($expectedData, $data['units']);
    }

    /**
     * @return array
     */
    public function productUnitsDataProvider()
    {
        return [
            'product.1 full' => [
                'product.1',
                [
                    'bottle' => 'bottle',
                    'liter' => 'liter',
                    'milliliter' => 'milliliter',
                ],
                false
            ],
            'product.2 full' => [
                'product.2',
                [
                    'bottle' => 'bottle',
                    'box' => 'box',
                    'liter' => 'liter',
                    'milliliter' => 'milliliter',
                ],
                false
            ],
            'product.1 short' => [
                'product.1',
                [
                    'bottle' => 'bottle',
                    'liter' => 'liter',
                    'milliliter' => 'milliliter',
                ],
                true
            ],
            'product.2 short' => [
                'product.2',
                [
                    'bottle' => 'bottle',
                    'box' => 'box',
                    'liter' => 'liter',
                    'milliliter' => 'milliliter',
                ],
                true
            ]
        ];
    }

    /**
     * @param string $reference
     * @return Product
     */
    protected function getProduct($reference)
    {
        return $this->getReference($reference);
    }

    /**
     * @param string $reference
     * @return ProductUnit
     */
    protected function getProductUnit($reference)
    {
        return $this->getReference($reference);
    }
}
