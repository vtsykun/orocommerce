<?php

namespace Oro\Bundle\PricingBundle\Event\CombinedPriceList;

use Symfony\Component\EventDispatcher\Event;

class AccountCPLUpdateEvent extends Event
{
    const NAME = 'oro_pricing.account.combined_price_list.update';

    /**
     * @var array
     */
    protected $accountsData;

    /**
     * @param array $accountsData
     */
    public function __construct(array $accountsData)
    {
        $this->accountsData = $accountsData;
    }

    /**
     * @return array
     */
    public function getAccountsData()
    {
        return $this->accountsData;
    }
}
