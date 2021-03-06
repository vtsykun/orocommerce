<?php

namespace Oro\Bundle\PaymentBundle\Twig;

use Oro\Bundle\PaymentBundle\Formatter\PaymentMethodLabelFormatter;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;

class PaymentMethodExtension extends \Twig_Extension
{
    const PAYMENT_METHOD_EXTENSION_NAME = 'oro_payment_method';

    /**
     * @var  PaymentTransactionProvider
     */
    protected $paymentTransactionProvider;

    /**
     * @var PaymentMethodLabelFormatter
     */
    protected $paymentMethodLabelFormatter;

    /**
     * @param PaymentTransactionProvider $paymentTransactionProvider
     * @param PaymentMethodLabelFormatter $paymentMethodLabelFormatter
     */
    public function __construct(
        PaymentTransactionProvider $paymentTransactionProvider,
        PaymentMethodLabelFormatter $paymentMethodLabelFormatter
    ) {
        $this->paymentTransactionProvider = $paymentTransactionProvider;
        $this->paymentMethodLabelFormatter = $paymentMethodLabelFormatter;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return static::PAYMENT_METHOD_EXTENSION_NAME;
    }

    /**
     * @return array
     */
    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('get_payment_methods', [$this, 'getPaymentMethods']),
            new \Twig_SimpleFunction(
                'get_payment_method_label',
                [$this->paymentMethodLabelFormatter, 'formatPaymentMethodLabel']
            ),
            new \Twig_SimpleFunction(
                'get_payment_method_admin_label',
                [$this->paymentMethodLabelFormatter, 'formatPaymentMethodAdminLabel'],
                ['is_safe' => ['html']]
            )
        ];
    }

    /**
     * @param object $entity
     * @return array
     */
    public function getPaymentMethods($entity)
    {
        $paymentTransactions = $this->paymentTransactionProvider->getPaymentTransactions($entity);
        $paymentMethods = [];
        foreach ($paymentTransactions as $paymentTransaction) {
            $paymentMethods[] = $this->paymentMethodLabelFormatter
                ->formatPaymentMethodLabel(
                    $paymentTransaction->getPaymentMethod(),
                    false
                );
        }

        return $paymentMethods;
    }
}
