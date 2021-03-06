<?php

namespace Oro\Bundle\ShoppingListBundle\Form\Type;

use Symfony\Bridge\Doctrine\ManagerRegistry;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Translation\TranslatorInterface;

use Oro\Bundle\CustomerBundle\Entity\AccountUser;
use Oro\Bundle\ShoppingListBundle\Entity\LineItem;
use Oro\Bundle\ShoppingListBundle\Entity\Repository\ShoppingListRepository;
use Oro\Bundle\ProductBundle\Form\Type\FrontendLineItemType;

class FrontendLineItemWidgetType extends AbstractType
{
    const NAME = 'oro_shopping_list_frontend_line_item_widget';

    /**
     * @var ManagerRegistry
     */
    protected $registry;

    /**
     * @var AccountUser
     */
    private $accountUser;

    /**
     * @var string
     */
    protected $shoppingListClass;

    /**
     * @var TokenStorage
     */
    protected $tokenStorage;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @param ManagerRegistry $registry
     * @param TokenStorage    $tokenStorage
     * @param TranslatorInterface $translator
     */
    public function __construct(ManagerRegistry $registry, TokenStorage $tokenStorage, TranslatorInterface $translator)
    {
        $this->registry = $registry;
        $this->tokenStorage = $tokenStorage;
        $this->translator = $translator;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add(
                'shoppingList',
                'entity',
                [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'oro.shoppinglist.lineitem.shopping_list.label',
                    'class' => $this->shoppingListClass,
                    'query_builder' => function (ShoppingListRepository $repository) {
                        return $repository->createFindForAccountUserQueryBuilder($this->getAccountUser());
                    },
                    'empty_value' => 'oro.shoppinglist.lineitem.create_new_shopping_list',
                ]
            )
            ->add(
                'shoppingListLabel',
                'text',
                [
                    'mapped' => false,
                    'required' => true,
                    'label' => 'oro.shoppinglist.lineitem.new_shopping_list_label'
                ]
            )
            ->addEventListener(FormEvents::POST_SET_DATA, [$this, 'postSetData'])
            ->addEventListener(FormEvents::POST_SUBMIT, [$this, 'postSubmit']);
    }

    /**
     * @param FormEvent $event
     */
    public function postSetData(FormEvent $event)
    {
        /* @var $lineItem LineItem */
        $lineItem = $event->getData();

        $event->getForm()->get('shoppingList')->setData($lineItem->getShoppingList());
    }

    /**
     * @param FormEvent $event
     */
    public function postSubmit(FormEvent $event)
    {
        $form = $event->getForm();

        if (!$form->get('shoppingList')->getData() && !$form->get('shoppingListLabel')->getData()) {
            $form->get('shoppingListLabel')->addError(
                new FormError($this->translator->trans('oro.shoppinglist.not_empty', [], 'validators'))
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        /** @var ShoppingListRepository $shoppingListRepository */
        $shoppingListRepository = $currentShoppingList = $this->registry
            ->getManagerForClass($this->shoppingListClass)
            ->getRepository($this->shoppingListClass);
        $currentShoppingList = $shoppingListRepository->findCurrentForAccountUser($this->getAccountUser());

        $view->children['shoppingList']->vars['currentShoppingList'] = $currentShoppingList;
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
        return self::NAME;
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return FrontendLineItemType::NAME;
    }

    /**
     * @param string $shoppingListClass
     */
    public function setShoppingListClass($shoppingListClass)
    {
        $this->shoppingListClass = $shoppingListClass;
    }

    /**
     * @return string|AccountUser
     */
    protected function getAccountUser()
    {
        if (!$this->accountUser) {
            $token = $this->tokenStorage->getToken();
            if ($token) {
                $this->accountUser = $token->getUser();
            }
        }

        if (!$this->accountUser || !is_object($this->accountUser)) {
            throw new AccessDeniedException();
        }

        return $this->accountUser;
    }
}
