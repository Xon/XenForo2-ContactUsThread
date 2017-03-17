<?php

namespace SV\ContactUsThread\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

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
		if ($this->app()->options()->svContactUsDiscardDiscourage && $this->isPost() && $this->isDiscouraged())
		{
			$redirect = $this->getDynamicRedirect(null, false);
			return $this->redirect($redirect, \XF::phrase('your_message_has_been_sent'));
		}
		return parent::actionContact();
	}
}