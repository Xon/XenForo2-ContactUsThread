<?php

namespace SV\ContactUsThread\XF\Pub\Controller;

use SV\ContactUsThread\Globals;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\View;
use XF\Mvc\Reply\Redirect as RedirectReply;
use XF\Phrase;
use XF\Util\Ip;

class Misc extends XFCP_Misc
{
    var $action = null;

    protected function preDispatchType($action, ParameterBag $params)
    {
        $this->action = strtolower($action);
        parent::preDispatchType($action, $params);
    }

    public function assertNotBanned()
    {
        if ($this->action == 'contact' && $this->app()->options()->svContactUsAllowBanned)
        {
            return;
        }

        parent::assertNotBanned();
    }

    public function actionContact()
    {
        $reply = parent::actionContact();

        if ($reply instanceof View && Globals::doLoginRedirect())
        {
            $reply->setParam('redirectToLoginController', 1);
        }
        else if ($this->isPost() && $reply instanceof RedirectReply && Globals::doLoginRedirect())
        {
            $redirectMessage = $reply->getMessage();
            if ($redirectMessage instanceof Phrase && $redirectMessage->getName() === 'your_message_has_been_sent')
            {
                $reply->setUrl($this->buildLink('login/contact'));
            }
        }

        return $reply;
    }

    protected function setupContactService()
    {
        /** @var \SV\ContactUsThread\XF\Service\Contact $form */
        $form = parent::setupContactService();

        $form->setDiscouraged($this->isDiscouraged());

        return $form;
    }

    public function assertNotFlooding($action, $floodingLimit = null)
    {
        $visitor = \XF::visitor();
        if ($action === 'contact' && !$visitor->hasPermission('general', 'bypassFloodCheck'))
        {
            $contactFloodingLimit = \XF::options()->svContactUsThreadRateLimit;
            if (!$contactFloodingLimit)
            {
                $contactFloodingLimit = $floodingLimit;
            }
            $userId = $visitor->user_id;
            if (!$userId)
            {
                // xf_flood_check.user_id is unsigned 32 bits integer.
                // Use the IP address crc32'ed as a stand-in for the userid to fit into the field.
                // set the high bit to ensure it is unlikely to cause a collision with a valid user
                $binaryIp = Ip::convertIpStringToBinary($this->app->request()->getIp());
                $userId = crc32($binaryIp) | (1 << 31);
            }

            /** @var \XF\Service\FloodCheck $floodChecker */
            $floodChecker = $this->service('XF:FloodCheck');
            $timeRemaining = $floodChecker->checkFlooding($action, $userId, $contactFloodingLimit);
            if ($timeRemaining)
            {
                throw $this->exception($this->error(\XF::phrase('contact_us_flooding', ['count' => $timeRemaining])));
            }

            return;
        }

        parent::assertNotFlooding($action, $floodingLimit);
    }
}