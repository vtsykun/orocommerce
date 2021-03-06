<?php

namespace Oro\Bundle\CustomerBundle\Tests\Functional\Controller\Frontend\Api\Rest;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Persistence\ObjectRepository;

use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Bundle\FrontendTestFrameworkBundle\Migrations\Data\ORM\LoadAccountUserData;
use Oro\Bundle\CustomerBundle\Entity\AccountUser;
use Oro\Bundle\CustomerBundle\Entity\AccountUserRole;
use Oro\Bundle\CustomerBundle\Tests\Functional\DataFixtures\LoadAccountUserRoleData;

/**
 * @dbIsolation
 */
class AccountUserRoleControllerTest extends WebTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->initClient(
            [],
            $this->generateBasicAuthHeader(LoadAccountUserData::AUTH_USER, LoadAccountUserData::AUTH_PW)
        );
        $this->client->useHashNavigation(true);
        $this->loadFixtures(
            [
                'Oro\Bundle\CustomerBundle\Tests\Functional\DataFixtures\LoadAccountUserRoleData'
            ]
        );
    }

    public function testDeletePredefinedRole()
    {
        $predefinedRole = $this->getRoleByLabel(LoadAccountUserRoleData::ROLE_EMPTY);
        $this->assertNotNull($predefinedRole);

        $this->client->request(
            'DELETE',
            $this->getUrl('oro_api_frontend_account_delete_accountuserrole', ['id' => $predefinedRole->getId()])
        );

        $result = $this->client->getResponse();
        $this->assertJsonResponseStatusCodeEquals($result, 403);

        $this->assertNotNull($this->getRoleByLabel(LoadAccountUserRoleData::ROLE_EMPTY));
    }

    public function testDeleteCustomizedRole()
    {
        $this->markTestSkipped('Should be fixed after BB-4518');
        $currentUser = $this->getCurrentUser();
        $currentUser->setAccount($this->getReference('account.orphan'));
        $this->getObjectManager()->flush();


        /** @var AccountUserRole $customizedRole */
        $customizedRole = $this->getRoleByLabel(LoadAccountUserRoleData::ROLE_WITH_ACCOUNT);
        $this->assertNotNull($customizedRole);

        $this->client->request(
            'DELETE',
            $this->getUrl('oro_api_frontend_account_delete_accountuserrole', ['id' => $customizedRole->getId()])
        );

        $result = $this->client->getResponse();

        $this->assertEmptyResponseStatusCodeEquals($result, 204);
        $this->assertNull($this->getRoleByLabel(LoadAccountUserRoleData::ROLE_WITH_ACCOUNT));
    }

    /**
     * @return ObjectManager
     */
    protected function getObjectManager()
    {
        return $this->getContainer()->get('doctrine')->getManager();
    }

    /**
     * @return ObjectRepository
     */
    protected function getUserRoleRepository()
    {
        return $this->getObjectManager()->getRepository('OroCustomerBundle:AccountUserRole');
    }

    /**
     * @return ObjectRepository
     */
    protected function getUserRepository()
    {
        return $this->getObjectManager()->getRepository('OroCustomerBundle:AccountUser');
    }

    /**
     * @param string $label
     * @return AccountUserRole
     */
    protected function getRoleByLabel($label)
    {
        return $this->getUserRoleRepository()
            ->findOneBy(['label' => $label]);
    }

    /**
     * @return AccountUser
     */
    protected function getCurrentUser()
    {
        return $this->getUserRepository()->findOneBy(['username' => LoadAccountUserData::AUTH_USER]);
    }
}
