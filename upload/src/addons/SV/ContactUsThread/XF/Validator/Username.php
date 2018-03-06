<?php

namespace SV\ContactUsThread\XF\Validator;

class Username extends XFCP_Username
{
    static $nextRequest = false;

    public function setupOptionDefaults()
    {
        parent::setupOptionDefaults();
        if (self::$nextRequest)
        {
            self::$nextRequest = false;
            $this->setOption('admin_edit', true);
            $this->setOption('check_unique', false);
        }
    }

    public function setOption($key, $value)
    {
        if ($key == 'force_next_validator_request')
        {
            self::$nextRequest = true;

            return;
        }
        parent::setOption($key, $value);
    }
}