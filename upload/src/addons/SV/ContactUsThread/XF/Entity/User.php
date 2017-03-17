<?php

namespace SV\ContactUsThread\XF\Entity;

class User extends XFCP_User
{
	public function canUseContactForm()
	{
		$options = $this->app()->options();

		if ($options->svContactUsAllowBanned && $this->is_banned && $options->contactUrl['type'])
		{
			return true;
		}
		return parent::canUseContactForm();
	}
}