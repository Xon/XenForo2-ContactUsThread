<?php

namespace SV\ContactUsThread\XF\Pub\Controller;

use SV\ContactUsThread\Globals;
use XF\Mvc\ParameterBag;

/**
 * Extends \XF\Pub\Controller\Login
 */
class Login extends XFCP_Login
{
    var $action = null;

    protected function preDispatchType($action, ParameterBag $params)
    {
        $this->action = strtolower($action);
        parent::preDispatchType($action, $params);
    }

    public function assertNotBanned()
    {
        if ($this->action == 'contact' && $this->app()->options()->svContactUsAllowBanned &&
            Globals::doLoginRedirect())
        {
            return;
        }

        parent::assertNotBanned();
    }

    public function actionContact()
    {
        if (!Globals::doLoginRedirect())
        {
            return $this->notFound();
        }
        /** @var Misc $controller */
        $controller = \XF::app()->controller('XF:Misc', $this->request);
        return $controller->actionContact();
    }
}