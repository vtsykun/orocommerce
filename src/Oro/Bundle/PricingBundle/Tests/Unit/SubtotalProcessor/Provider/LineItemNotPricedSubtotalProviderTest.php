<?php

namespace Oro\Bundle\PricingBundle\Tests\Unit\SubtotalProcessor\Provider;

use Doctrine\ORM\EntityManager;

use Symfony\Component\Translation\TranslatorInterface;

use Oro\Component\Testing\Unit\EntityTrait;
use Oro\Bundle\CurrencyBundle\Entity\Price;
use Oro\Bundle\CurrencyBundle\Rounding\RoundingServiceInterface;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\PricingBundle\Entity\BasePriceList;
use Oro\Bundle\PricingBundle\Model\PriceListTreeHandler;
use Oro\Bundle\PricingBundle\Provider\ProductPriceProvider;
use Oro\Bundle\PricingBundle\SubtotalProcessor\Provider\LineItemNotPricedSubtotalProvider;
use Oro\Bundle\PricingBundle\Tests\Unit\SubtotalProcessor\Stub\EntityNotPricedStub;
use Oro\Bundle\PricingBundle\Tests\Unit\SubtotalProcessor\Stub\LineItemNotPricedStub;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Entity\ProductUnit;

class LineItemNotPricedSubtotalProviderTest extends AbstractSubtotalProviderTest
{
    use EntityTrait;

    /**
     * @var LineItemNotPricedSubtotalProvider
     */
    protected $provider;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|TranslatorInterface
     */
    protected $translator;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|RoundingServiceInterface
     */
    protected $roundingService;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|ProductPriceProvider
     */
    protected $productPriceProvider;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|DoctrineHelper
     */
    protected $doctrineHelper;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|PriceListTreeHandler
     */
    protected $priceListTreeHandler;

    protected function setUp()
    {
        parent::setUp();
        $this->translator = $this->getMock('Symfony\Component\Translation\TranslatorInterface');

        $this->roundingService = $this->getMock('Oro\Bundle\CurrencyBundle\Rounding\RoundingServiceInterface');
        $this->roundingService->expects($this->any())
            ->method('round')
            ->will(
                $this->returnCallback(
                    function ($value) {
                        return round($value, 0, PHP_ROUND_HALF_UP);
                    }
                )
            );
        $this->productPriceProvider = $this->getMockBuilder('Oro\Bundle\PricingBundle\Provider\ProductPriceProvider')
            ->disableOriginalConstructor()
            ->getMock();

        $this->doctrineHelper = $this->getMockBuilder('Oro\Bundle\EntityBundle\ORM\DoctrineHelper')
            ->disableOriginalConstructor()
            ->getMock();

        $this->priceListTreeHandler = $this->getMockBuilder('Oro\Bundle\PricingBundle\Model\PriceListTreeHandler')
            ->disableOriginalConstructor()
            ->getMock();

        $this->provider = new LineItemNotPricedSubtotalProvider(
            $this->translator,
            $this->roundingService,
            $this->productPriceProvider,
            $this->doctrineHelper,
            $this->priceListTreeHandler,
            $this->currencyManager
        );
    }

    protected function tearDown()
    {
        unset($this->translator, $this->provider);
    }

    public function testGetSubtotal()
    {
        $value = 142.0;
        $currency = 'USD';
        $identifier = '1-code-2-USD';

        $this->translator->expects($this->once())
            ->method('trans')
            ->with(LineItemNotPricedSubtotalProvider::NAME . '.label')
            ->willReturn('test');

        $product = $this->prepareProduct();
        $productUnit = $this->prepareProductUnit();
        $this->prepareEntityManager($product, $productUnit);
        $this->preparePrice($value, $identifier);

        $entity = new EntityNotPricedStub();
        $lineItem = new LineItemNotPricedStub();
        $lineItem->setProduct($product);
        $lineItem->setProductUnit($productUnit);
        $lineItem->setQuantity(2);

        $entity->addLineItem($lineItem);

        $entity->setCurrency($currency);

        /** @var BasePriceList $priceList */
        $priceList = $this->getEntity('Oro\Bundle\PricingBundle\Entity\BasePriceList', ['id' => 1]);

        $this->priceListTreeHandler->expects($this->exactly($entity->getLineItems()->count()))
            ->method('getPriceList')
            ->with($entity->getAccount(), $entity->getWebsite())
            ->willReturn($priceList);

        $subtotal = $this->provider->getSubtotal($entity);
        $this->assertInstanceOf('Oro\Bundle\PricingBundle\SubtotalProcessor\Model\Subtotal', $subtotal);
        $this->assertEquals(LineItemNotPricedSubtotalProvider::TYPE, $subtotal->getType());
        $this->assertEquals('test', $subtotal->getLabel());
        $this->assertEquals($entity->getCurrency(), $subtotal->getCurrency());
        $this->assertInternalType('float', $subtotal->getAmount());
        $this->assertEquals($value * 2, $subtotal->getAmount());
        $this->assertTrue($subtotal->isVisible());
    }

    public function testGetSubtotalWithoutLineItems()
    {
        $this->translator->expects($this->once())
            ->method('trans')
            ->with(LineItemNotPricedSubtotalProvider::NAME . '.label')
            ->willReturn('test');

        $entity = new EntityNotPricedStub();

        $subtotal = $this->provider->getSubtotal($entity);
        $this->assertInstanceOf('Oro\Bundle\PricingBundle\SubtotalProcessor\Model\Subtotal', $subtotal);
        $this->assertEquals(LineItemNotPricedSubtotalProvider::TYPE, $subtotal->getType());
        $this->assertEquals('test', $subtotal->getLabel());
        $this->assertEquals($entity->getCurrency(), $subtotal->getCurrency());
        $this->assertInternalType('float', $subtotal->getAmount());
        $this->assertEquals(0, $subtotal->getAmount());
        $this->assertFalse($subtotal->isVisible());
    }

    public function testGetName()
    {
        $this->assertEquals(LineItemNotPricedSubtotalProvider::NAME, $this->provider->getName());
    }

    public function testIsSupported()
    {
        $entity = new EntityNotPricedStub();
        $this->assertTrue($this->provider->isSupported($entity));
    }

    public function testIsNotSupported()
    {
        $entity = new LineItemNotPricedStub();
        $this->assertFalse($this->provider->isSupported($entity));
    }

    /**
     * @return ProductUnit|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function prepareProductUnit()
    {
        /** @var ProductUnit|\PHPUnit_Framework_MockObject_MockObject $productUnit */
        $productUnit = $this->getMockBuilder('Oro\Bundle\ProductBundle\Entity\ProductUnit')
            ->disableOriginalConstructor()
            ->getMock();
        $productUnit->expects($this->any())
            ->method('getCode')
            ->willReturn('code');

        return $productUnit;
    }

    /**
     * @return Product|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function prepareProduct()
    {
        /** @var Product|\PHPUnit_Framework_MockObject_MockObject $product */
        $product = $this->getMockBuilder('Oro\Bundle\ProductBundle\Entity\Product')
            ->disableOriginalConstructor()
            ->getMock();
        $product->expects($this->any())
            ->method('getId')
            ->willReturn(1);

        return $product;
    }

    /**
     * @param Product$product
     * @param ProductUnit $productUnit
     */
    protected function prepareEntityManager(Product $product, ProductUnit $productUnit)
    {
        /* @var $entityManager EntityManager|\PHPUnit_Framework_MockObject_MockObject */
        $entityManager = $this->getMockBuilder('Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();
        $this->doctrineHelper->expects($this->any())
            ->method('getEntityManagerForClass')
            ->willReturn($entityManager);
        $entityManager->expects($this->at(0))
            ->method('getReference')
            ->willReturn($product);
        $entityManager->expects($this->at(1))
            ->method('getReference')
            ->willReturn($productUnit);
    }

    /**
     * @param float $value
     * @param string $identifier
     */
    protected function preparePrice($value, $identifier)
    {
        /** @var Price|\PHPUnit_Framework_MockObject_MockObject $price */
        $price = $this->getMockBuilder('Oro\Bundle\CurrencyBundle\Entity\Price')
            ->disableOriginalConstructor()
            ->getMock();
        $price->expects($this->any())
            ->method('getValue')
            ->willReturn($value);
        $this->productPriceProvider->expects($this->any())
            ->method('getMatchedPrices')
            ->willReturn([$identifier => $price]);
    }
}
