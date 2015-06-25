<?php

namespace OroB2B\Bundle\CustomerBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class AccountUserPasswordResetType extends AbstractType
{
    const NAME = 'orob2b_customer_account_user_password_reset';

    /**
     * @var string
     */
    protected $dataClass;

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add(
            'plainPassword',
            'repeated',
            [
                'type' => 'password',
                'first_options' => ['label' => 'orob2b.customer.accountuser.password.label'],
                'second_options' => ['label' => 'orob2b.customer.accountuser.password_confirmation.label'],
                'invalid_message' => 'orob2b.customer.message.password_mismatch',
                'required' => true,
                'validation_groups' => ['create']
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults([
            'data_class' => $this->dataClass,
            'intention'  => 'account_user_reset',
            'dynamic_fields_disabled' => true
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return self::NAME;
    }

    /**
     * @param string $dataClass
     * @return AccountUserPasswordResetType
     */
    public function setDataClass($dataClass)
    {
        $this->dataClass = $dataClass;

        return $this;
    }
}
