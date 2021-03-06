<?php

namespace Oro\Bundle\CMSBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

use Oro\Bundle\EntityBundle\EntityProperty\DatesAwareInterface;
use Oro\Bundle\EntityBundle\EntityProperty\DatesAwareTrait;
use Oro\Bundle\EntityConfigBundle\Metadata\Annotation\Config;
use Oro\Bundle\EntityConfigBundle\Metadata\Annotation\ConfigField;
use Oro\Bundle\OrganizationBundle\Entity\Ownership\AuditableOrganizationAwareTrait;
use Oro\Bundle\CMSBundle\Model\ExtendPage;
use Oro\Bundle\RedirectBundle\Entity\Slug;

/**
 * @ORM\Table(name="oro_cms_page")
 * @ORM\Entity()
 * @Config(
 *      routeName="oro_cms_page_index",
 *      routeView="oro_cms_page_view",
 *      routeUpdate="oro_cms_page_update",
 *      defaultValues={
 *          "entity"={
 *              "icon"="icon-book"
 *          },
 *          "dataaudit"={
 *              "auditable"=true
 *          },
 *         "ownership"={
 *              "owner_type"="ORGANIZATION",
 *              "owner_field_name"="organization",
 *              "owner_column_name"="organization_id"
 *          },
 *          "security"={
 *              "type"="ACL",
 *              "group_name"=""
 *          },
 *          "form"={
 *              "form_type"="oro_cms_page_select",
 *              "grid_name"="cms-page-select-grid"
 *          }
 *      }
 * )
 */
class Page extends ExtendPage implements DatesAwareInterface
{
    use AuditableOrganizationAwareTrait;
    use DatesAwareTrait;

    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255)
     * @ConfigField(
     *      defaultValues={
     *          "dataaudit"={
     *              "auditable"=true
     *          }
     *      }
     * )
     */
    protected $title;

    /**
     * @var string
     *
     * @ORM\Column(type="text", nullable=true)
     * @ConfigField(
     *      defaultValues={
     *          "dataaudit"={
     *              "auditable"=true
     *          }
     *      }
     * )
     */
    protected $content;

    /**
     * @var Slug
     *
     * @ORM\OneToOne(targetEntity="Oro\Bundle\RedirectBundle\Entity\Slug", cascade={"ALL"})
     * @ORM\JoinColumns({
     *     @ORM\JoinColumn(name="current_slug_id", referencedColumnName="id")
     * })
     * @ConfigField(
     *      defaultValues={
     *          "dataaudit"={
     *              "auditable"=true
     *          }
     *      }
     * )
     */
    protected $currentSlug;

    /**
     * @var Collection|Slug[]
     *
     * @ORM\ManyToMany(
     *      targetEntity="Oro\Bundle\RedirectBundle\Entity\Slug",
     *      cascade={"ALL"},
     *      orphanRemoval=true
     * )
     * @ORM\JoinTable(name="oro_cms_page_to_slug",
     *      joinColumns={
     *          @ORM\JoinColumn(name="page_id", referencedColumnName="id", onDelete="CASCADE")
     *      },
     *      inverseJoinColumns={
     *          @ORM\JoinColumn(name="slug_id", referencedColumnName="id", unique=true, onDelete="CASCADE")
     *      }
     * )
     */
    protected $slugs;

    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        parent::__construct();

        $this->slugs = new ArrayCollection();
        $this->setCurrentSlug(new Slug());
    }

    /**
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param string $title
     * @return $this
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @param string $content
     * @return $this
     */
    public function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Set current slug
     *
     * @param Slug $currentSlug
     * @return $this
     */
    public function setCurrentSlug(Slug $currentSlug)
    {
        $this->currentSlug = $currentSlug;
        $this->addSlug($currentSlug);
        $this->refreshSlugUrls();

        return $this;
    }

    /**
     * Get slugs related to current page
     *
     * @return Slug[]
     */
    public function getRelatedSlugs()
    {
        return array_diff($this->slugs->toArray(), [$this->currentSlug]);
    }

    /**
     * Get currentSlug
     *
     * @return Slug
     */
    public function getCurrentSlug()
    {
        return $this->currentSlug;
    }

    /**
     * @param $url
     * @return $this
     */
    public function setCurrentSlugUrl($url)
    {
        $this->currentSlug->setUrl($url);
        $this->refreshSlugUrls();

        return $this;
    }

    /**
     * @return string
     */
    public function getCurrentSlugUrl()
    {
        return $this->currentSlug->getUrl();
    }

    /**
     * @return Collection|Slug[]
     */
    public function getSlugs()
    {
        return $this->slugs;
    }

    /**
     * @param Slug $slug
     * @return $this
     */
    public function addSlug(Slug $slug)
    {
        if (!$this->slugs->contains($slug)) {
            $this->slugs->add($slug);
        }

        return $this;
    }

    /**
     * @param Slug $slug
     * @return $this
     */
    public function removeSlug(Slug $slug)
    {
        if ($this->slugs->contains($slug)) {
            $this->slugs->removeElement($slug);
        }

        return $this;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return (string)$this->getTitle();
    }

    /**
     * Refresh slug URLs for current and child pages
     */
    protected function refreshSlugUrls()
    {
        $slugUrl = $this->currentSlug->getSlugUrl();
        $this->currentSlug->setUrl(Slug::DELIMITER . $slugUrl);
    }
}
