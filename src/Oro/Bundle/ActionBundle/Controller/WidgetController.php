<?php

namespace Oro\Bundle\ActionBundle\Controller;

use Doctrine\Common\Collections\ArrayCollection;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

use Oro\Bundle\ActionBundle\Helper\ApplicationsHelper;
use Oro\Bundle\ActionBundle\Helper\ContextHelper;
use Oro\Bundle\ActionBundle\Model\ActionData;
use Oro\Bundle\ActionBundle\Model\ActionManager;

class WidgetController extends Controller
{
    /**
     * @Route("/buttons", name="oro_action_widget_buttons")
     * @Template()
     *
     * @return array
     */
    public function buttonsAction(Request $request)
    {
        return [
            'actions' => $this->getActionManager()->getActions(),
            'context' => $this->getContextHelper()->getContext(),
            'actionData' => $this->getContextHelper()->getActionData(),
            'dialogRoute' => $this->getApplicationsHelper()->getDialogRoute(),
            'executionRoute' => $this->getApplicationsHelper()->getExecutionRoute(),
            'fromUrl' => $request->get('fromUrl'),
        ];
    }

    /**
     * @Route("/form/{actionName}", name="oro_action_widget_form")
     *
     * @param Request $request
     * @param string $actionName
     * @return Response
     */
    public function formAction(Request $request, $actionName)
    {
        $data = $this->getContextHelper()->getActionData();
        $errors = new ArrayCollection();
        $params = [];

        try {
            /** @var Form $form */
            $form = $this->get('oro_action.form_manager')->getActionForm($actionName, $data);
            $form->handleRequest($request);

            if ($form->isValid()) {
                $data = $this->getActionManager()->execute($actionName, $form->getData(), $errors);

                $params['response'] = $this->getResponse($data);
            }

            $params['form'] = $form->createView();
            $params['context'] = $data->getValues();
            $params['action'] = $this->getActionManager()->getAction($actionName, $data);
            $params['fromUrl'] = $request->get('fromUrl');

        } catch (\Exception $e) {
            if (!$errors->count()) {
                $errors->add(['message' => $e->getMessage()]);
            }
        }

        $params['errors'] = $errors;

        return $this->render($this->getActionManager()->getFrontendTemplate($actionName), $params);
    }

    /**
     * @return ActionManager
     */
    protected function getActionManager()
    {
        return $this->get('oro_action.manager');
    }

    /**
     * @return ContextHelper
     */
    protected function getContextHelper()
    {
        return $this->get('oro_action.helper.context');
    }

    /**
     * @return ApplicationsHelper
     */
    protected function getApplicationsHelper()
    {
        return $this->get('oro_action.helper.applications');
    }

    /**
     * @param ActionData $context
     * @return array
     */
    protected function getResponse(ActionData $context)
    {
        /* @var $session Session */
        $session = $this->get('session');

        $response = [];
        if ($context->getRedirectUrl()) {
            $response['redirectUrl'] = $context->getRedirectUrl();
        } elseif ($context->getRefreshGrid()) {
            $response['refreshGrid'] = $context->getRefreshGrid();
            $response['flashMessages'] = $session->getFlashBag()->all();
        }

        return $response;
    }
}
