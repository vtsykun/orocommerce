<?php

namespace Oro\Bundle\CustomerBundle\Tests\Functional\Controller\Frontend\Api\Rest;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Persistence\ObjectRepository;

use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Bundle\FrontendTestFrameworkBundle\Migrations\Data\ORM\LoadAccountUserData as LoadLoginAccountUserData;

/**
 * @dbIsolation
 */
class AccountUserControllerTest extends WebTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->initClient(
            [],
            $this->generateBasicAuthHeader(LoadLoginAccountUserData::AUTH_USER, LoadLoginAccountUserData::AUTH_PW)
        );
        $this->client->useHashNavigation(true);
        $this->loadFixtures(
            [
                'Oro\Bundle\CustomerBundle\Tests\Functional\DataFixtures\LoadAccountUserData'
            ]
        );
    }

    public function testDelete()
    {
        /** @var \Oro\Bundle\CustomerBundle\Entity\AccountUser $user */
        $user = $this->getUserRepository()->findOneBy(['email' => 'account.user2@test.com']);

        $this->assertNotNull($user);
        $id = $user->getId();

        $this->client->request(
            'DELETE',
            $this->getUrl('oro_api_account_frontend_delete_account_user', ['id' => $id])
        );
        $result = $this->client->getResponse();
        $this->assertEmptyResponseStatusCodeEquals($result, 204);

        $this->getObjectManager()->clear();
        $user = $this->getUserRepository()->find($id);

        $this->assertNull($user);
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
    protected function getUserRepository()
    {
        return $this->getObjectManager()->getRepository('OroCustomerBundle:AccountUser');
    }
}
