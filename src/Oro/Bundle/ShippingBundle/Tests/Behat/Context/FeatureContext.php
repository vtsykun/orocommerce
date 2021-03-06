<?php

namespace Oro\Bundle\ShippingBundle\Tests\Behat\Context;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Behat\Symfony2Extension\Context\KernelAwareContext;
use Behat\Symfony2Extension\Context\KernelDictionary;
use Doctrine\Common\Persistence\ObjectManager;
use Oro\Bundle\CheckoutBundle\Tests\Behat\Element\CheckoutStep;
use Oro\Bundle\DataGridBundle\Tests\Behat\Element\Grid;
use Oro\Bundle\NavigationBundle\Tests\Behat\Element\MainMenu;
use Oro\Bundle\ShoppingListBundle\Entity\ShoppingList;
use Oro\Bundle\TestFrameworkBundle\Behat\Context\OroFeatureContext;
use Oro\Bundle\TestFrameworkBundle\Behat\Element\Form;
use Oro\Bundle\TestFrameworkBundle\Behat\Element\OroPageObjectAware;
use Oro\Bundle\TestFrameworkBundle\Tests\Behat\Context\PageObjectDictionary;
use Oro\Bundle\TestFrameworkBundle\Tests\Behat\Context\OroMainContext;

class FeatureContext extends OroFeatureContext implements OroPageObjectAware, KernelAwareContext
{
    use PageObjectDictionary, KernelDictionary;

    /**
     * @var OroMainContext
     */
    private $oroMainContext;

    /**
     * @BeforeScenario
     */
    public function gatherContexts(BeforeScenarioScope $scope)
    {
        $environment = $scope->getEnvironment();
        $this->oroMainContext = $environment->getContext(OroMainContext::class);
    }

    /**
     * @Given there is EUR currency in the system configuration
     */
    public function thereIsEurCurrencyInTheSystemConfiguration()
    {
        $configManager = $this->getContainer()->get('oro_config.global');
        /** @var array $currencies */
        $currencies = (array) $configManager->get('oro_currency.allowed_currencies', []);
        $currencies = array_unique(array_merge($currencies, ['EUR']));
        $configManager->set('oro_currency.allowed_currencies', $currencies);
        $configManager->set('oro_pricing.enabled_currencies', ['EUR', 'USD']);
        $configManager->flush();
    }

    /**
     * @When /^Buyer is on Checkout step on (?P<shoppingListName>[\w\s]+)$/
     */
    public function buyerIsOnShippingMethodCheckoutStep($shoppingListName)
    {
        $this->createOrderFromShoppingList($shoppingListName);

        /** @var checkoutStep $checkoutStep */
        $checkoutStep = $this->createElement('CheckoutStep');
        $checkoutStep->assertTitle('Billing Information');
        $this->waitForAjax();

        $this->getSession()->getPage()->pressButton('Continue');
        $this->waitForAjax();
        $this->getSession()->getPage()->pressButton('Continue');
        $this->waitForAjax();
        $checkoutStep->assertTitle('Shipping Method');
    }

    /**
     * @Then Shipping Type :shippingType is shown for Buyer selection
     */
    public function shippingTypeFlatRateIsShownForBuyerSelection($shippingType)
    {
        $element= $this->createElement('CheckoutFormRow');
        self::assertNotFalse(
            strpos($element->getText(), $shippingType),
            "Shipping type '$shippingType' not found on checkout form"
        );
    }

    /**
     * @Then the order total is recalculated to :total
     */
    public function theOrderTotalIsRecalculatedTo($total)
    {
        $this->waitForAjax();
        $totalElement = $this->createElement('CheckoutTotalSum');
        if (!$totalElement->isVisible()) {
            $this->createElement('CheckoutTotalTrigger')->click();
        }
        self::assertEquals($total, $totalElement->getText());
    }

    /**
     * @Then There is no shipping method available for this order
     */
    public function noShippingMethodsAvailable()
    {
        $this->assertSession()->elementContains('css', '.notification_alert', 'No shipping methods are available');
    }

    /**
     * @Given Admin User edited :shippingRule with next data:
     */
    public function adminUserEditedWithNextData($shippingRule, TableNode $table)
    {
        $this->getMink()->setDefaultSessionName('second_session');
        $this->getSession()->resizeWindow(1920, 1880, 'current');

        $this->oroMainContext->loginAsUserWithPassword();
        $this->waitForAjax();
        /** @var MainMenu $mainMenu */
        $mainMenu = $this->createElement('MainMenu');
        $mainMenu->openAndClick('System/Shipping Rules');
        $this->waitForAjax();

        /** @var Grid $grid */
        $grid = $this->createElement('Grid');
        $grid->clickActionLink($shippingRule, 'Edit');
        $this->waitForAjax();

        /** @var Form $form */
        $form = $this->createElement('Shipping Rule');
        if (in_array('Country2', $table->getColumn(0), true)) {
            $destinationAdd = $form->find('css', '.add-list-item');
            $destinationAdd->click();
        }
        $form->fill($table);
        $form->saveAndClose();
        $this->waitForAjax();

        $this->getSession('second_session')->stop();
        $this->getMink()->setDefaultSessionName('first_session');
    }

    /**
     * @Given Admin User created :shoppingRuleName with next data:
     */
    public function adminUserCreatedWithNextData($shoppingRuleName, TableNode $table)
    {
        $this->getMink()->setDefaultSessionName('second_session');
        $this->getSession()->resizeWindow(1920, 1880, 'current');

        $this->oroMainContext->loginAsUserWithPassword();
        $this->waitForAjax();

        /** @var MainMenu $mainMenu */
        $mainMenu = $this->createElement('MainMenu');
        $mainMenu->openAndClick('System/Shipping Rules');
        $this->waitForAjax();

        $this->getSession()->getPage()->clickLink('Create Shipping Rule');
        $this->waitForAjax();

        /** @var Form $form */
        $form = $this->createElement('Shipping Rule');
        $form->fillField('Name', $shoppingRuleName);

        // Add method type config
        if (in_array('Type', $table->getColumn(0), true)) {
            $shippingMethodConfigAdd = $form->find('css', '.add-method');
            $shippingMethodConfigAdd->click();
            $this->waitForAjax();
        }

        if (in_array('Country2', $table->getColumn(0), true)) {
            $form->fillField('Sort Order', '1');
            $destinationAdd = $form->find('css', '.add-list-item');
            $destinationAdd->click();
        }

        $form->fill($table);
        $form->saveAndClose();

        $this->waitForAjax();
        $this->getSession('second_session')->stop();
        $this->getMink()->setDefaultSessionName('first_session');
    }

    /**
     * @When Buyer is again on Shipping Method Checkout step on :arg1
     */
    public function buyerIsAgainOnShippingMethodCheckoutStepOn($shoppingListName)
    {
        $this->createOrderFromShoppingList($shoppingListName);
        /** @var checkoutStep $checkoutStep */
        $checkoutStep = $this->createElement('CheckoutStep');
        $checkoutStep->assertTitle('Shipping Method');
    }

    /**
     * @param string $shoppingListName
     */
    protected function createOrderFromShoppingList($shoppingListName)
    {
        /** @var ObjectManager $manager */
        $manager = $this->getContainer()->get('doctrine')->getManagerForClass(ShoppingList::class);
        /** @var ShoppingList $shoppingList */
        $shoppingList = $manager->getRepository(ShoppingList::class)->findOneBy(['label' => $shoppingListName]);
        $this->visitPath('account/shoppinglist/'.$shoppingList->getId());
        $this->waitForAjax();
        $this->getSession()->getPage()->clickLink('Create Order');
        $this->waitForAjax();
    }

    /**
     * @When Buyer created order with next shipping address:
     */
    public function buyerCreatedOrderWithNextShippingAddress(TableNode $table)
    {
        /** @var checkoutStep $checkoutStep */
        $checkoutStep = $this->createElement('CheckoutStep');
        $checkoutStep->assertTitle('Shipping Method');

        $this->getSession()->getPage()->clickLink('Back');
        $this->waitForAjax();
        $checkoutStep->assertTitle('Shipping Information');

        /** @var Form $form */
        $form = $this->createElement('Address');
        $form->fillField('SELECT SHIPPING ADDRESS', 'Enter other address');
        $this->waitForAjax();
        /** @var int $row */
        if ($row = array_search('Country', $table->getColumn(0))) {
            $form->fillField('Country', $table->getRow($row)[1]);
            $this->waitForAjax();
        }
        $form->fill($table);
        $this->getSession()->getPage()->pressButton('Continue');
    }
}
