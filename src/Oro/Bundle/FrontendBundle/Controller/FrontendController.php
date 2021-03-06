<?php

namespace Oro\Bundle\FrontendBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Oro\Bundle\LayoutBundle\Annotation\Layout;

class FrontendController extends Controller
{
    /**
     * @Layout
     * @Route("/", name="oro_frontend_root")
     */
    public function indexAction()
    {
        return [];
    }

    /**
     * @Layout()
     * @Route("/exception/{code}/{text}", name="oro_frontend_exception", requirements={"code"="\d+"})
     * @param int $code
     * @param string $text
     * @return array
     */
    public function exceptionAction($code, $text)
    {
        return [
            'data' => [
                'status_code' => $code,
                'status_text' => $text,
            ]
        ];
    }
}
