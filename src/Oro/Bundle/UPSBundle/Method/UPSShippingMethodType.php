<?php

namespace Oro\Bundle\UPSBundle\Method;

use Oro\Bundle\ShippingBundle\Context\ShippingContextInterface;
use Oro\Bundle\ShippingBundle\Method\ShippingMethodTypeInterface;
use Oro\Bundle\UPSBundle\Cache\ShippingPriceCache;
use Oro\Bundle\UPSBundle\Entity\ShippingService;
use Oro\Bundle\UPSBundle\Entity\UPSTransport;
use Oro\Bundle\UPSBundle\Factory\PriceRequestFactory;
use Oro\Bundle\UPSBundle\Form\Type\UPSShippingMethodOptionsType;
use Oro\Bundle\UPSBundle\Provider\UPSTransport as UPSTransportProvider;

class UPSShippingMethodType implements ShippingMethodTypeInterface
{
    const REQUEST_OPTION = 'Rate';

    /** @var string */
    protected $methodId;

    /** @var UPSTransport */
    protected $transport;

    /** @var UPSTransportProvider */
    protected $transportProvider;

    /** @var ShippingService */
    protected $shippingService;

    /** @var PriceRequestFactory */
    protected $priceRequestFactory;

    /** @var ShippingPriceCache */
    protected $cache;

    /**
     * @param string $methodId
     * @param UPSTransport $transport
     * @param UPSTransportProvider $transportProvider
     * @param ShippingService $shippingService
     * @param PriceRequestFactory $priceRequestFactory
     * @param ShippingPriceCache $cache
     */
    public function __construct(
        $methodId,
        UPSTransport $transport,
        UPSTransportProvider $transportProvider,
        ShippingService $shippingService,
        PriceRequestFactory $priceRequestFactory,
        ShippingPriceCache $cache
    ) {
        $this->methodId = $methodId;
        $this->transport = $transport;
        $this->transportProvider = $transportProvider;
        $this->shippingService = $shippingService;
        $this->priceRequestFactory = $priceRequestFactory;
        $this->cache = $cache;
    }


    /**
     * {@inheritdoc}
     */
    public function getIdentifier()
    {
        return $this->shippingService->getCode();
    }

    /**
     * {@inheritdoc}
     */
    public function getLabel()
    {
        return $this->shippingService->getDescription();
    }

    /**
     * {@inheritdoc}
     */
    public function getSortOrder()
    {
        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getOptionsConfigurationFormType()
    {
        return UPSShippingMethodOptionsType::class;
    }

    /**
     * @return ShippingService
     */
    public function getShippingService()
    {
        return $this->shippingService;
    }

    /**
     * {@inheritdoc}
     */
    public function calculatePrice(ShippingContextInterface $context, array $methodOptions, array $typeOptions)
    {
        $priceRequest = $this->priceRequestFactory->create(
            $this->transport,
            $context,
            static::REQUEST_OPTION,
            $this->shippingService
        );

        if (count($priceRequest->getPackages()) < 1) {
            return null;
        }

        $cacheKey = $this->cache->createKey($this->transport, $priceRequest, $this->methodId, $this->getIdentifier());
        if (!$this->cache->containsPrice($cacheKey)) {
            $priceResponse = $this->transportProvider->getPriceResponse($priceRequest, $this->transport);
            if (!$priceResponse) {
                return null;
            }
            $price = $priceResponse->getPriceByService($this->shippingService->getCode());
            if (!$price) {
                return null;
            }
            $this->cache->savePrice($cacheKey, $price);
        } else {
            $price = $this->cache->fetchPrice($cacheKey);
        }

        $optionsDefaults = [
            UPSShippingMethod::OPTION_SURCHARGE => 0,
        ];
        $methodOptions = array_merge($optionsDefaults, $methodOptions);
        $typeOptions = array_merge($optionsDefaults, $typeOptions);

        return $price->setValue(array_sum([
            (float)$price->getValue(),
            (float)$methodOptions[UPSShippingMethod::OPTION_SURCHARGE],
            (float)$typeOptions[UPSShippingMethod::OPTION_SURCHARGE]
        ]));
    }
}
