<?php

namespace Oro\Bundle\RFPBundle\Migrations\Data\Demo\ORM;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManager;

use Oro\Bundle\UserBundle\Entity\User;
use Oro\Bundle\CurrencyBundle\Entity\Price;
use Oro\Bundle\MigrationBundle\Fixture\AbstractEntityReferenceFixture;
use Oro\Bundle\CustomerBundle\Entity\AccountUser;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\RFPBundle\Entity\Request;
use Oro\Bundle\RFPBundle\Entity\RequestProduct;
use Oro\Bundle\RFPBundle\Entity\RequestProductItem;

class LoadRequestDemoData extends AbstractEntityReferenceFixture implements
    DependentFixtureInterface,
    ContainerAwareInterface
{
    /**
     * @var array
     */
    protected $requests = [];

    /**
     * @var ContainerInterface
     */
    protected $container = [];

    /** {@inheritdoc} */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function getDependencies()
    {
        return [
            'Oro\Bundle\CustomerBundle\Migrations\Data\Demo\ORM\LoadAccountUserDemoData',
            'Oro\Bundle\RFPBundle\Migrations\Data\Demo\ORM\LoadRequestStatusDemoData',
            'Oro\Bundle\ProductBundle\Migrations\Data\Demo\ORM\LoadProductUnitPrecisionDemoData',
        ];
    }

    /**
     * @param EntityManager $manager
     * {@inheritdoc}
     */
    public function load(ObjectManager $manager)
    {
        $statuses     = $this->getObjectReferences($manager, 'OroRFPBundle:RequestStatus');
        $organization = $manager->getRepository('OroOrganizationBundle:Organization')->getFirst();

        $accountUsers = $this->getAccountUsers($manager);

        /** @var User $user */
        $owner = $manager->getRepository('OroUserBundle:User')->findOneBy([]);

        $locator  = $this->container->get('file_locator');
        $filePath = $locator->locate('@OroRFPBundle/Migrations/Data/Demo/ORM/data/requests.csv');
        if (is_array($filePath)) {
            $filePath = current($filePath);
        }

        $handler = fopen($filePath, 'r');
        $headers = fgetcsv($handler, 5000, ',');

        while (($data = fgetcsv($handler, 5000, ',')) !== false) {
            $row = array_combine($headers, array_values($data));

            $accountUser = $accountUsers[rand(0, count($accountUsers) - 1)];
            $poNumber = 'CA' . rand(1000, 9999) . 'USD';

            $request = new Request();
            $request
                ->setFirstName($row['first_name'])
                ->setLastName($row['last_name'])
                ->setEmail($row['email'])
                ->setPhone($row['phone'])
                ->setCompany($row['company'])
                ->setRole($row['role'])
                ->setNote($row['note'])
                ->setShipUntil(new \DateTime('+10 day'))
                ->setPoNumber($poNumber)
                ->setAccountUser($accountUser)
                ->setAccount($accountUser ? $accountUser->getAccount() : null)
            ;

            $status = $statuses[rand(0, count($statuses) - 1)];
            $request->setStatus($status);
            $request->setOwner($owner);
            $request->setOrganization($organization);

            $this->processRequestProducts($request, $manager);

            $manager->persist($request);
        }

        fclose($handler);

        $manager->flush();
    }

    /**
     * @param ObjectManager $manager
     * @return Product[]
     */
    protected function getProducts(ObjectManager $manager)
    {
        $products = $manager->getRepository('OroProductBundle:Product')->findBy([], null, 10);

        if (0 === count($products)) {
            throw new \LogicException('There are no products in system');
        }

        return $products;
    }

    /**
     * @return array
     */
    protected function getCurrencies()
    {
        $currencies = $this->container->get('oro_config.manager')->get('oro_currency.allowed_currencies');

        if (!$currencies) {
            $currencies = (array)$this->container->get('oro_locale.settings')->getCurrency();
        }

        if (!$currencies) {
            throw new \LogicException('There are no currencies in system');
        }

        return $currencies;
    }

    /**
     * @param ObjectManager $manager
     * @return AccountUser[]
     */
    protected function getAccountUsers(ObjectManager $manager)
    {
        return array_merge([null], $manager->getRepository('OroCustomerBundle:AccountUser')->findBy([], null, 10));
    }

    /**
     * @param Request $request
     * @param ObjectManager $manager
     */
    protected function processRequestProducts(Request $request, ObjectManager $manager)
    {
        $products = $this->getProducts($manager);
        $currencies = $this->getCurrencies();
        $numLineItems = rand(1, 10);
        for ($i = 0; $i < $numLineItems; $i++) {
            $product = $products[rand(0, count($products) - 1)];
            $unitPrecisions = $product->getUnitPrecisions();

            if (!count($unitPrecisions)) {
                continue;
            }

            $requestProduct = new RequestProduct();
            $requestProduct->setProduct($product);
            $requestProduct->setComment(sprintf('Notes %s', $i));
            $numProductItems = rand(1, 10);
            for ($j = 0; $j < $numProductItems; $j++) {
                $productUnit = $unitPrecisions[rand(0, count($unitPrecisions) - 1)]->getUnit();

                $currency = $currencies[rand(0, count($currencies) - 1)];
                $requestProductItem = new RequestProductItem();
                $requestProductItem
                    ->setPrice(Price::create(rand(1, 100), $currency))
                    ->setQuantity(rand(1, 100))
                    ->setProductUnit($productUnit)
                ;
                $requestProduct->addRequestProductItem($requestProductItem);
            }
            $request->addRequestProduct($requestProduct);
        }
    }
}
