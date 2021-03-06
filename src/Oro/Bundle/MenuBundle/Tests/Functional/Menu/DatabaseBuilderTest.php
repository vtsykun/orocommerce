<?php

namespace Oro\Bundle\MenuBundle\Tests\Functional\Menu;

use Knp\Menu\Util\MenuManipulator;

use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Bundle\MenuBundle\Entity\MenuItem;
use Oro\Bundle\MenuBundle\Menu\DatabaseBuilder;

/**
 * @dbIsolation
 */
class DatabaseBuilderTest extends WebTestCase
{
    /**
     * @var DatabaseBuilder
     */
    protected $builder;

    public function setUp()
    {
        $this->initClient();
        $this->client->useHashNavigation(true);

        $this->loadFixtures(['Oro\Bundle\MenuBundle\Tests\Functional\DataFixtures\LoadMenuItemData']);

        $container = $this->getContainer();

        $this->builder = new DatabaseBuilder(
            $container->get('doctrine'),
            $container->get('oro_menu.menu.factory')
        );
    }

    /**
     * @dataProvider buildDataProvider
     * @param string $alias
     * @param string $localization
     * @param array $expectedData
     */
    public function testBuild($alias, $localization, array $expectedData)
    {
        $options = [];
        if ($localization) {
            $options['extras'][MenuItem::LOCALE_OPTION] = $this->getReference($localization);
        }
        $menuItem = $this->builder->build($alias, $options);
        $actualData = (new MenuManipulator())->toArray($menuItem);
        $this->assertEquals($this->prepareExpectedData($expectedData), $actualData);
    }

    /**
     * @return array
     */
    public function buildDataProvider()
    {
        return [
            [
                'alias' => 'menu_item.1',
                'localization' => null,
                'expectedData' => [
                    'name' => 'menu_item.1',
                    'label' => 'menu_item.1',
                    'uri' => null,
                    'children' => [
                        'menu_item.1_2' => [
                            'name' => 'menu_item.1_2',
                            'label' => 'menu_item.1_2',
                        ],
                        'menu_item.1_3' => [
                            'name' => 'menu_item.1_3',
                            'label' => 'menu_item.1_3',
                        ],
                    ],
                ],
            ],
            [
                'alias' => 'menu_item.1',
                'localization' => 'en_CA',
                'expectedData' => [
                    'name' => 'menu_item.1',
                    'label' => 'menu_item.1',
                    'uri' => null,
                    'children' => [
                        'menu_item.1_2' => [
                            'name' => 'menu_item.1_2',
                            'label' => 'menu_item.1_2.en_CA',
                        ],
                        'menu_item.1_3' => [
                            'name' => 'menu_item.1_3',
                            'label' => 'menu_item.1_3.en_CA',
                        ],
                    ],
                ],
            ]
        ];
    }

    /**
     * @dataProvider isSupportedDataProvider
     * @param string $alias
     * @param bool $expectedSupport
     */
    public function testIsSupported($alias, $expectedSupport)
    {
        $this->assertEquals($expectedSupport, $this->builder->isSupported($alias));
    }

    /**
     * @return array
     */
    public function isSupportedDataProvider()
    {
        return [
            [
                'alias' => 'menu_item.1',
                'expectedSupport' => true,
            ],
            [
                'alias' => 'not exists',
                'expectedSupport' => false,
            ]
        ];
    }

    /**
     * @param array $data
     * @return array
     */
    protected function prepareExpectedData(array $data)
    {
        $data = array_merge($this->getDefaultItem(), $data);
        foreach ($data['children'] as &$child) {
            $child = $this->prepareExpectedData($child);
        }
        return $data;
    }

    /**
     * @return array
     */
    protected function getDefaultItem()
    {
        return [
            'uri' => '#',
            'attributes' => [],
            'labelAttributes' => [],
            'linkAttributes' => [],
            'childrenAttributes' => [],
            'extras' => ['isAllowed' => true],
            'display' => true,
            'displayChildren' => true,
            'current' => null,
            'children' => [],
        ];
    }
}
