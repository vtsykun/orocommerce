<?php

namespace Oro\Bundle\RFPBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class RequestStatusTranslationType extends AbstractType
{
    const NAME = 'oro_rfp_request_status_translation';

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['labels'] = $options['labels'];
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(
            [
                'translatable_class'   => 'Oro\\Bundle\\RFPBundle\\Entity\\RequestStatus',
                'intention'            => 'request_status_translation',
                'extra_fields_message' => 'This form should not contain extra fields: "{{ extra_fields }}"',
                'cascade_validation'   => true,
                'labels'               => [],
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return 'a2lix_translations_gedmo';
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->getBlockPrefix();
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return static::NAME;
    }
}
