<?php

namespace Oro\Bundle\CustomerBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

use Oro\Bundle\NavigationBundle\Entity\AbstractNavigationItem;
use Oro\Bundle\UserBundle\Entity\AbstractUser;

/**
 * Navigation Entity
 *
 * @ORM\Entity(repositoryClass="Oro\Bundle\NavigationBundle\Entity\Repository\NavigationItemRepository")
 * @ORM\HasLifecycleCallbacks
 * @ORM\Table(name="oro_acc_navigation_item",
 *      indexes={@ORM\Index(name="oro_sorted_items_idx", columns={"account_user_id", "position"})})
 */
class NavigationItem extends AbstractNavigationItem
{
    /**
     * @var AbstractUser $user
     *
     * @ORM\ManyToOne(targetEntity="Oro\Bundle\CustomerBundle\Entity\AccountUser")
     * @ORM\JoinColumn(name="account_user_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    protected $user;

    /**
     * @var string $type
     *
     * @ORM\Column(name="type", type="string", length=20, nullable=false)
     */
    protected $type;
}
