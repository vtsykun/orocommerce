<?php

namespace OroB2B\Bundle\TaxBundle\Tests\Functional\Controller;

use Symfony\Component\DomCrawler\Crawler;

use Oro\Bundle\EntityExtendBundle\Entity\AbstractEnumValue;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;

use OroB2B\Bundle\TaxBundle\Entity\AccountTaxCode;
use OroB2B\Bundle\TaxBundle\Tests\Functional\DataFixtures\LoadAccountTaxCodes;
use OroB2B\Bundle\AccountBundle\Entity\Account;
use OroB2B\Bundle\AccountBundle\Entity\AccountGroup;

/**
 * @dbIsolation
 */
class AccountControllerTest extends WebTestCase
{
    const ACCOUNT_NAME = 'Account_name';
    const UPDATED_NAME = 'Account_name_UP';

    protected function setUp()
    {
        $this->initClient([], $this->generateBasicAuthHeader());

        $this->loadFixtures(
            [
                'OroB2B\Bundle\AccountBundle\Tests\Functional\DataFixtures\LoadAccounts',
                'OroB2B\Bundle\AccountBundle\Tests\Functional\DataFixtures\LoadGroups',
                'OroB2B\Bundle\AccountBundle\Tests\Functional\DataFixtures\LoadInternalRating',
                'OroB2B\Bundle\TaxBundle\Tests\Functional\DataFixtures\LoadAccountTaxCodes'
            ]
        );
    }

    public function testCreate()
    {
        $crawler = $this->client->request('GET', $this->getUrl('orob2b_account_create'));
        $result = $this->client->getResponse();
        $this->assertHtmlResponseStatusCodeEquals($result, 200);

        /** @var Account $parent */
        $parent = $this->getReference('account.level_1');
        /** @var AccountGroup $group */
        $group = $this->getReference('account_group.group1');
        /** @var AbstractEnumValue $internalRating */
        $internalRating = $this->getReference('internal_rating.1 of 5');
        /** @var AccountTaxCode $accountTaxCode */
        $accountTaxCode = $this->getReference(LoadAccountTaxCodes::REFERENCE_PREFIX . '.' . LoadAccountTaxCodes::TAX_1);

        $this->assertAccountSave($crawler, self::ACCOUNT_NAME, $parent, $group, $internalRating, $accountTaxCode);
    }

    /**
     * @depends testCreate
     * @param int $id
     */
    public function testView($id)
    {
        $response = $this->client->requestGrid(
            'account-accounts-grid',
            ['account-accounts-grid[_filter][name][value]' => self::ACCOUNT_NAME]
        );

        $result = $this->getJsonResponseContent($response, 200);
        $result = reset($result['data']);

        $id = $result['id'];

        $crawler = $this->client->request(
            'GET',
            $this->getUrl('orob2b_account_view', ['id' => $id])
        );

        $result = $this->client->getResponse();
        $this->assertHtmlResponseStatusCodeEquals($result, 200);

        $html = $crawler->html();

        /** @var AccountTaxCode $accountTaxCode */
        $accountTaxCode = $this->getReference(LoadAccountTaxCodes::REFERENCE_PREFIX . '.' . LoadAccountTaxCodes::TAX_1);

        $this->assertContains($accountTaxCode->getCode(), $html);
    }

    /**
     * @param Crawler           $crawler
     * @param string            $name
     * @param Account           $parent
     * @param AccountGroup      $group
     * @param AbstractEnumValue $internalRating
     * @param AccountTaxCode    $accountTaxCode
     */
    protected function assertAccountSave(
        Crawler $crawler,
        $name,
        Account $parent,
        AccountGroup $group,
        AbstractEnumValue $internalRating,
        AccountTaxCode $accountTaxCode
    ) {
        $form = $crawler->selectButton('Save and Close')->form(
            [
                'orob2b_account_type[name]' => $name,
                'orob2b_account_type[parent]' => $parent->getId(),
                'orob2b_account_type[group]' => $group->getId(),
                'orob2b_account_type[internal_rating]' => $internalRating->getId(),
                'orob2b_account_type[taxCode]' => $accountTaxCode->getId(),
            ]
        );

        $this->client->followRedirects(true);
        $crawler = $this->client->submit($form);

        $result = $this->client->getResponse();
        $this->assertHtmlResponseStatusCodeEquals($result, 200);
        $html = $crawler->html();

        $this->assertContains('Account has been saved', $html);
        $this->assertViewPage($html, $name, $parent, $group, $internalRating, $accountTaxCode);
    }

    /**
     * @param string            $html
     * @param string            $name
     * @param Account           $parent
     * @param AccountGroup      $group
     * @param AbstractEnumValue $internalRating
     * @param AccountTaxCode    $accountTaxCode
     */
    protected function assertViewPage(
        $html,
        $name,
        Account $parent,
        AccountGroup $group,
        AbstractEnumValue $internalRating,
        AccountTaxCode $accountTaxCode
    ) {
        $groupName = $group->getName();
        $this->assertContains($name, $html);
        $this->assertContains($parent->getName(), $html);
        $this->assertContains($groupName, $html);
        $this->assertContains($internalRating->getName(), $html);
        $this->assertContains($accountTaxCode->getCode(), $html);
    }
}
