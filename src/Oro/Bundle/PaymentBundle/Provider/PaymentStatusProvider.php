<?php

namespace Oro\Bundle\PaymentBundle\Provider;

use Doctrine\Common\Collections\ArrayCollection;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PricingBundle\SubtotalProcessor\Model\Subtotal;
use Oro\Bundle\PricingBundle\SubtotalProcessor\TotalProcessorProvider;

class PaymentStatusProvider
{
    const FULL = 'full';
    const AUTHORIZED = 'authorized';
    const PENDING = 'pending';
    const DECLINED = 'declined';
    const PARTIALLY = 'partially';

    /** @var PaymentTransactionProvider */
    protected $paymentTransactionProvider;

    /** @var TotalProcessorProvider */
    protected $totalProcessorProvider;

    /**
     * @param PaymentTransactionProvider $paymentTransactionProvider
     * @param TotalProcessorProvider $totalProcessorProvider
     */
    public function __construct(
        PaymentTransactionProvider $paymentTransactionProvider,
        TotalProcessorProvider $totalProcessorProvider
    ) {
        $this->paymentTransactionProvider = $paymentTransactionProvider;
        $this->totalProcessorProvider = $totalProcessorProvider;
    }

    /**
     * @param object $object
     * @return string
     */
    public function getPaymentStatus($object)
    {
        $paymentTransactions = new ArrayCollection($this->paymentTransactionProvider->getPaymentTransactions($object));
        
        return $this->computeStatus($object, $paymentTransactions);
    }

    /**
     * @param object $object
     * @param ArrayCollection $paymentTransactions
     * @return string
     */
    public function computeStatus($object, ArrayCollection $paymentTransactions)
    {
        $total = $this->totalProcessorProvider->getTotal($object);

        if ($this->hasSuccessfulTransactions($paymentTransactions, $total)) {
            return self::FULL;
        }

        if ($this->hasPartialTransactions($paymentTransactions, $total)) {
            return self::PARTIALLY;
        }

        if ($this->hasAuthorizeTransactions($paymentTransactions)) {
            return self::AUTHORIZED;
        }

        if ($this->hasDeclinedTransactions($paymentTransactions)) {
            return self::DECLINED;
        }

        return self::PENDING;
    }
    
    /**
     * @param ArrayCollection $paymentTransactions
     * @return ArrayCollection
     */
    protected function getSuccessfulTransactions(ArrayCollection $paymentTransactions)
    {
        return $paymentTransactions
            ->filter(
                function (PaymentTransaction $paymentTransaction) {
                    return $paymentTransaction->isSuccessful()
                    && in_array(
                        $paymentTransaction->getAction(),
                        [
                            PaymentMethodInterface::CAPTURE,
                            PaymentMethodInterface::CHARGE,
                            PaymentMethodInterface::PURCHASE,
                        ],
                        true
                    );
                }
            );
    }

    /**
     * @param ArrayCollection $paymentTransactions
     * @return float
     */
    protected function getTransactionAmounts(ArrayCollection $paymentTransactions)
    {
        $amounts = $paymentTransactions->map(function (PaymentTransaction $paymentTransaction) {
            return $paymentTransaction->getAmount();
        });

        return array_sum($amounts->toArray());
    }

    /**
     * @param ArrayCollection $paymentTransactions
     * @param Subtotal $total
     * @return bool
     */
    protected function hasSuccessfulTransactions(ArrayCollection $paymentTransactions, Subtotal $total)
    {
        $successfulTransactions = $this->getSuccessfulTransactions($paymentTransactions);
        $transactionAmount = $this->getTransactionAmounts($successfulTransactions);

        return $successfulTransactions->count() && $transactionAmount >= $total->getAmount();
    }

    /**
     * @param ArrayCollection $paymentTransactions
     * @param Subtotal $total
     * @return bool
     */
    protected function hasPartialTransactions(ArrayCollection $paymentTransactions, Subtotal $total)
    {
        $successfulTransactions = $this->getSuccessfulTransactions($paymentTransactions);
        $transactionAmount = $this->getTransactionAmounts($successfulTransactions);

        return $successfulTransactions->count() && $transactionAmount < $total->getAmount();
    }

    /**
     * @param ArrayCollection $paymentTransactions
     * @return bool
     */
    protected function hasAuthorizeTransactions(ArrayCollection $paymentTransactions)
    {
        return false === $paymentTransactions
            ->filter(
                function (PaymentTransaction $paymentTransaction) {
                    if ($paymentTransaction->isClone()) {
                        return false;
                    }

                    return $paymentTransaction->isActive()
                    && $paymentTransaction->isSuccessful()
                    && $paymentTransaction->getAction() === PaymentMethodInterface::AUTHORIZE;
                }
            )
            ->isEmpty();
    }

    /**
     * @param ArrayCollection $paymentTransactions
     * @return bool
     */
    protected function hasDeclinedTransactions(ArrayCollection $paymentTransactions)
    {
        return $paymentTransactions->count() > 0 && $paymentTransactions
            ->filter(
                function (PaymentTransaction $paymentTransaction) {
                    return !$paymentTransaction->isSuccessful() && !$paymentTransaction->isActive();
                }
            )->count() === $paymentTransactions->count();
    }
}
