<?php

namespace Oro\Bundle\PricingBundle\Tests\Unit\Form\Extension\Stub;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

use Oro\Bundle\CustomerBundle\Form\Type\AccountGroupType;

class AccountGroupTypeStub extends AbstractType
{
    /**
     * @return string
     */
    public function getName()
    {
        return AccountGroupType::NAME;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('name', 'text', ['label' => 'oro.customer_group.name.label']);
    }
}
