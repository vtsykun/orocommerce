<?php

namespace Oro\Bundle\CustomerBundle\Tests\Unit\Form\Type;

use Symfony\Component\Form\FormView;

use Oro\Bundle\CustomerBundle\Entity\Account;
use Oro\Bundle\CustomerBundle\Form\Type\ParentAccountSelectType;

class ParentAccountSelectTypeTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ParentAccountSelectType
     */
    protected $type;

    protected function setUp()
    {
        $this->type = new ParentAccountSelectType();
    }

    public function testGetName()
    {
        $this->assertEquals(ParentAccountSelectType::NAME, $this->type->getName());
    }

    public function testGetParent()
    {
        $this->assertEquals('oro_jqueryselect2_hidden', $this->type->getParent());
    }

    public function testConfigureOptions()
    {
        $resolver = $this->getMock('Symfony\Component\OptionsResolver\OptionsResolver');
        $resolver->expects($this->once())
            ->method('setDefaults')
            ->with($this->isType('array'))
            ->willReturnCallback(
                function (array $options) {
                    $this->assertArrayHasKey('autocomplete_alias', $options);
                    $this->assertArrayHasKey('configs', $options);
                    $this->assertEquals('oro_account_parent', $options['autocomplete_alias']);
                    $this->assertEquals(
                        [
                            'component' => 'autocomplete-entity-parent',
                            'placeholder' => 'oro.customer.account.form.choose_parent'
                        ],
                        $options['configs']
                    );
                }
            );

        $this->type->configureOptions($resolver);
    }

    /**
     * @param object|null $parentData
     * @param int|null $expectedParentId
     * @dataProvider buildViewDataProvider
     */
    public function testBuildView($parentData, $expectedParentId)
    {
        $parentForm = $this->getMock('Symfony\Component\Form\FormInterface');
        $parentForm->expects($this->any())
            ->method('getData')
            ->willReturn($parentData);

        $formView = new FormView();

        $form = $this->getMock('Symfony\Component\Form\FormInterface');
        $form->expects($this->any())
            ->method('getParent')
            ->willReturn($parentForm);

        $this->type->buildView($formView, $form, []);

        $this->assertArrayHasKey('configs', $formView->vars);
        $this->assertArrayHasKey('entityId', $formView->vars['configs']);
        $this->assertEquals($expectedParentId, $formView->vars['configs']['entityId']);
    }

    /**
     * @return array
     */
    public function buildViewDataProvider()
    {
        $accountId = 42;
        $account = new Account();

        $reflection = new \ReflectionProperty(get_class($account), 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($account, $accountId);

        return [
            'without account' => [
                'parentData' => null,
                'expectedParentId' => null,
            ],
            'with account' => [
                'parentData' => $account,
                'expectedParentId' => $accountId,
            ],
        ];
    }
}
