<?php

namespace Oro\Bundle\ProductBundle\EventListener;

use Oro\Bundle\LocaleBundle\Entity\Localization;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\WebsiteBundle\Provider\AbstractWebsiteLocalizationProvider;
use Oro\Bundle\WebsiteBundle\Provider\WebsiteLocalizationProvider;
use Oro\Bundle\WebsiteSearchBundle\Event\IndexEntityEvent;
use Oro\Bundle\WebsiteSearchBundle\Manager\WebsiteContextManager;
use Oro\Bundle\WebsiteSearchBundle\Placeholder\LocalizationIdPlaceholder;

class WebsiteSearchProductIndexerListener
{
    /**
     * @var WebsiteContextManager
     */
    private $websiteContextManger;

    /**
     * @var WebsiteLocalizationProvider
     */
    private $websiteLocalizationProvider;

    /**
     * @param AbstractWebsiteLocalizationProvider $websiteLocalizationProvider
     * @param WebsiteContextManager $websiteContextManager
     */
    public function __construct(
        AbstractWebsiteLocalizationProvider $websiteLocalizationProvider,
        WebsiteContextManager $websiteContextManager
    ) {
        $this->websiteLocalizationProvider = $websiteLocalizationProvider;
        $this->websiteContextManger = $websiteContextManager;
    }

    /**
     * @param IndexEntityEvent $event
     */
    public function onWebsiteSearchIndex(IndexEntityEvent $event)
    {
        $websiteId = $this->websiteContextManger->getWebsiteId($event->getContext());
        if (!$websiteId) {
            $event->stopPropagation();

            return;
        }

        /** @var Product[] $products */
        $products = $event->getEntities();

        $localizations = $this->websiteLocalizationProvider->getLocalizationsByWebsiteId($websiteId);

        foreach ($products as $product) {
            // Non localized fields
            $event->addField($product->getId(), 'product_id', $product->getId());
            $event->addField($product->getId(), 'sku', $product->getSku());
            $event->addField($product->getId(), 'sku_uppercase', strtoupper($product->getSku()));
            $event->addField($product->getId(), 'status', $product->getStatus());
            $event->addField($product->getId(), 'inventory_status', $product->getInventoryStatus()->getId());


            // Localized fields
            $placeholders = [LocalizationIdPlaceholder::NAME => Localization::DEFAULT_LOCALIZATION];
            $event->addPlaceholderField(
                $product->getId(),
                'name_LOCALIZATION_ID',
                (string)$product->getDefaultName(),
                $placeholders
            );

            $event->addPlaceholderField(
                $product->getId(),
                'description_LOCALIZATION_ID',
                (string)$product->getDefaultDescription(),
                $placeholders
            );

            $event->addPlaceholderField(
                $product->getId(),
                'short_description_LOCALIZATION_ID',
                (string)$product->getDefaultShortDescription(),
                $placeholders
            );

            foreach ($localizations as $localization) {
                $localizationId = $localization->getId();
                $placeholders = [LocalizationIdPlaceholder::NAME => $localizationId];
                $event->addPlaceholderField(
                    $product->getId(),
                    'name_LOCALIZATION_ID',
                    (string)$product->getName($localization),
                    $placeholders
                );

                $event->addPlaceholderField(
                    $product->getId(),
                    'description_LOCALIZATION_ID',
                    (string)$product->getDescription($localization),
                    $placeholders
                );

                $event->addPlaceholderField(
                    $product->getId(),
                    'short_description_LOCALIZATION_ID',
                    (string)$product->getShortDescription($localization),
                    $placeholders
                );
            }
        }
    }
}
