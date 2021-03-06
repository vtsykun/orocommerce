<?php

namespace Oro\Bundle\ShippingBundle\Context;

use Oro\Bundle\CurrencyBundle\Entity\Price;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Entity\ProductUnit;
use Oro\Bundle\ProductBundle\Model\ProductHolderInterface;
use Oro\Bundle\ShippingBundle\Model\Dimensions;
use Oro\Bundle\ShippingBundle\Model\Weight;

class ShippingLineItem implements ShippingLineItemInterface
{
    /**
     * @var Product
     */
    private $product;

    /**
     * @var ProductHolderInterface
     */
    private $productHolder;

    /**
     * @var integer
     */
    private $quantity;

    /**
     * @var ProductUnit
     */
    private $productUnit;

    /**
     * @var Price
     */
    private $price;

    /**
     * @var Weight
     */
    private $weight;

    /**
     * @var Dimensions
     */
    private $dimensions;

    /**
     * @return Price
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * @param Price $price
     * @return $this
     */
    public function setPrice(Price $price = null)
    {
        $this->price = $price;

        return $this;
    }

    /**
     * Get product
     *
     * @return Product
     */
    public function getProduct()
    {
        return $this->product;
    }

    /**
     * @param Product $product
     * @return $this
     */
    public function setProduct(Product $product = null)
    {
        $this->product = $product;

        return $this;
    }

    /**
     * Get productSku
     *
     * @return string
     * @throws \InvalidArgumentException
     */
    public function getProductSku()
    {
        if ($this->product === null) {
            throw new \InvalidArgumentException('product is not defined.');
        }

        return $this->product->getSku();
    }

    /**
     * Get id
     *
     * @return mixed
     * @throws \InvalidArgumentException
     */
    public function getEntityIdentifier()
    {
        return $this->productHolder ? $this->productHolder->getEntityIdentifier() : null;
    }

    /**
     * Get productHolder
     *
     * @return ProductHolderInterface
     */
    public function getProductHolder()
    {
        return $this->productHolder;
    }

    /**
     * @param ProductHolderInterface $holder
     * @return $this
     */
    public function setProductHolder(ProductHolderInterface $holder = null)
    {
        $this->productHolder = $holder;
        return $this;
    }

    /**
     * Get product
     *
     * @return ProductUnit
     */
    public function getProductUnit()
    {
        return $this->productUnit;
    }

    /**
     * @param ProductUnit $productUnit
     * @return $this
     */
    public function setProductUnit(ProductUnit $productUnit = null)
    {
        $this->productUnit = $productUnit;

        return $this;
    }

    /**
     * Get productUnitCode
     *
     * @return string
     * @throws \InvalidArgumentException
     */
    public function getProductUnitCode()
    {
        if ($this->productUnit === null) {
            throw new \InvalidArgumentException('productUnit is not defined.');
        }

        return $this->productUnit->getCode();
    }

    /**
     * @return int
     */
    public function getQuantity()
    {
        return $this->quantity;
    }

    /**
     * @param $quantity
     * @return $this
     */
    public function setQuantity($quantity)
    {
        $this->quantity = $quantity;

        return $this;
    }

    /**
     * @return Weight|null
     */
    public function getWeight()
    {
        return $this->weight;
    }

    /**
     * @param Weight $weight
     * @return $this
     */
    public function setWeight(Weight $weight = null)
    {
        $this->weight = $weight;

        return $this;
    }

    /**
     * @return Dimensions|null
     */
    public function getDimensions()
    {
        return $this->dimensions;
    }

    /**
     * @param Dimensions $dimensions
     * @return $this
     */
    public function setDimensions(Dimensions $dimensions = null)
    {
        $this->dimensions = $dimensions;

        return $this;
    }
}
