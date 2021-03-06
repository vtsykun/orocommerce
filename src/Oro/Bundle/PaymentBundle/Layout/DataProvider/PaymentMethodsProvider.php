<?php

namespace Oro\Bundle\PaymentBundle\Layout\DataProvider;

use Oro\Bundle\PaymentBundle\Method\PaymentMethodRegistry;
use Oro\Bundle\PaymentBundle\Method\View\PaymentMethodViewRegistry;
use Oro\Bundle\PaymentBundle\Provider\PaymentContextProvider;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;

class PaymentMethodsProvider
{
    /**
     * @var array[]
     */
    protected $paymentMethodViews;

    /** @var PaymentMethodViewRegistry */
    protected $paymentMethodViewRegistry;

    /** @var PaymentContextProvider */
    protected $paymentContextProvider;

    /** @var PaymentMethodRegistry */
    protected $paymentMethodRegistry;

    /** @var PaymentTransactionProvider */
    protected $paymentTransactionProvider;

    /**
     * @param PaymentMethodViewRegistry $paymentMethodViewRegistry
     * @param PaymentContextProvider $paymentContextProvider
     * @param PaymentMethodRegistry $paymentMethodRegistry
     * @param PaymentTransactionProvider $transactionProvider
     */
    public function __construct(
        PaymentMethodViewRegistry $paymentMethodViewRegistry,
        PaymentContextProvider $paymentContextProvider,
        PaymentMethodRegistry $paymentMethodRegistry,
        PaymentTransactionProvider $transactionProvider
    ) {
        $this->paymentMethodViewRegistry = $paymentMethodViewRegistry;
        $this->paymentContextProvider = $paymentContextProvider;
        $this->paymentMethodRegistry = $paymentMethodRegistry;
        $this->paymentTransactionProvider = $transactionProvider;
    }

    /**
     * @param $entity
     * @return array[]
     */
    public function getViews($entity = null)
    {
        if (null === $this->paymentMethodViews) {
            $paymentContext = $this->paymentContextProvider->processContext($entity);

            $views = $this->paymentMethodViewRegistry->getPaymentMethodViews($paymentContext);
            foreach ($views as $view) {
                $this->paymentMethodViews[$view->getPaymentMethodType()] = [
                    'label' => $view->getLabel(),
                    'block' => $view->getBlock(),
                    'options' => $view->getOptions($paymentContext),
                ];
            }
        }

        return $this->paymentMethodViews;
    }

    /**
     * @param $paymentMethod
     * @param $entity
     * @return array[]
     */
    public function getView($paymentMethod, $entity = null)
    {
        if (!$this->paymentMethodViews) {
            $this->getViews($entity);
        }

        if ($this->isPaymentMethodEnabled($paymentMethod) &&
            $this->isPaymentMethodApplicable($paymentMethod, $entity)
        ) {
            return $this->paymentMethodViews[$paymentMethod];
        }
        return null;
    }

    /**
     * @param $paymentMethod
     * @return bool
     */
    public function isPaymentMethodEnabled($paymentMethod)
    {
        try {
            return $this->paymentMethodRegistry->getPaymentMethod($paymentMethod)->isEnabled();
        } catch (\InvalidArgumentException $e) {
        }

        return false;
    }

    /**
     * @param $paymentMethodName
     * @param $entity
     * @return bool
     */
    public function isPaymentMethodApplicable($paymentMethodName, $entity)
    {
        try {
            $paymentMethod = $this->paymentMethodRegistry->getPaymentMethod($paymentMethodName);
        } catch (\InvalidArgumentException $e) {
            return false;
        }

        if (!$paymentMethod->isEnabled()) {
            return false;
        }
        $paymentContext = $this->paymentContextProvider->processContext($entity);

        return $paymentMethod->isApplicable($paymentContext);
    }

    /**
     * @param $entity
     * @return bool
     */
    public function hasApplicablePaymentMethods($entity)
    {
        $paymentContext = $this->paymentContextProvider->processContext($entity);

        $paymentMethods = $this->paymentMethodRegistry->getPaymentMethods();
        foreach ($paymentMethods as $paymentMethod) {
            if (!$paymentMethod->isEnabled()) {
                continue;
            }

            if (!$paymentMethod->isApplicable($paymentContext)) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @param object $entity
     * @return array
     */
    public function getPaymentMethods($entity)
    {
        return $this->paymentTransactionProvider->getPaymentMethods($entity);
    }
}
