<?php

namespace Oro\Bundle\PricingBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

use Oro\Bundle\CustomerBundle\Entity\Account;

/**
 * @ORM\Table(
 *      name="oro_price_list_acc_fb",
 *      uniqueConstraints={
 *          @ORM\UniqueConstraint(name="oro_price_list_acc_fb_unq", columns={
 *              "account_id",
 *              "website_id"
 *          })
 *      }
 * )
 * @ORM\Entity(repositoryClass="Oro\Bundle\PricingBundle\Entity\Repository\PriceListAccountFallbackRepository")
 */
class PriceListAccountFallback extends PriceListFallback
{
    const ACCOUNT_GROUP = 0;
    const CURRENT_ACCOUNT_ONLY = 1;

    /** @var Account
     *
     * @ORM\ManyToOne(targetEntity="Oro\Bundle\CustomerBundle\Entity\Account")
     * @ORM\JoinColumn(name="account_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    protected $account;

    /**
     * @return Account
     */
    public function getAccount()
    {
        return $this->account;
    }

    /**
     * @param Account $account
     *
     * @return $this
     */
    public function setAccount($account)
    {
        $this->account = $account;

        return $this;
    }
}
