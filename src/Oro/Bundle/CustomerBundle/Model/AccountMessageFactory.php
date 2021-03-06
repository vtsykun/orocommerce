<?php

namespace Oro\Bundle\CustomerBundle\Model;

use Doctrine\Common\Persistence\ManagerRegistry;

use Oro\Bundle\CustomerBundle\Entity\Account;
use Oro\Bundle\CustomerBundle\Model\Exception\InvalidArgumentException;
use Oro\Bundle\VisibilityBundle\Model\MessageFactoryInterface;

class AccountMessageFactory implements MessageFactoryInterface
{
    const ID = 'id';

    /**
     * @var ManagerRegistry
     */
    protected $registry;

    /**
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @param Account $account
     * @return array
     */
    public function createMessage($account)
    {
        $message = [self::ID => null];
        if ($account instanceof Account) {
            $message[self::ID] = $account->getId();
        }

        return $message;
    }

    /**
     * @param array|null $data
     * @return Account
     */
    public function getEntityFromMessage($data)
    {
        $account = null;
        if (isset($data[self::ID])) {
            $account = $this->registry->getManagerForClass(Account::class)
                ->getRepository(Account::class)
                ->find($data[self::ID]);
        }
        if (!$account) {
            throw new InvalidArgumentException();
        }

        return $account;
    }
}
