<?php

namespace OroB2B\Bundle\WarehouseBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Oro\Bundle\SecurityBundle\Annotation\Acl;

use OroB2B\Bundle\ProductBundle\Entity\Product;

class WarehouseInventoryLevelController extends Controller
{
    /**
     * Edit product warehouse inventory levels
     *
     * @Route("/update/{id}", name="orob2b_warehouse_inventory_level_update", requirements={"id"="\d+"})
     * @Template
     * @Acl(
     *      id="orob2b_product_warehouse_inventory_update",
     *      type="entity",
     *      class="OroB2BWarehouseBundle:WarehouseInventoryLevel",
     *      permission="EDIT"
     * )
     *
     * @param Product $product
     * @return mixed
     */
    public function updateAction(Product $product)
    {
        return [
            'product' => $product
        ];
    }
}
