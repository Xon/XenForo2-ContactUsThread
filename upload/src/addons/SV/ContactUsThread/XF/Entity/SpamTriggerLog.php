<?php

namespace SV\ContactUsThread\XF\Entity;

use SV\ContactUsThread\Globals;

class SpamTriggerLog extends XFCP_SpamTriggerLog
{
    public function getDetails()
    {
        if (Globals::$spamTriggerDetailsAsArray)
        {
            $output = [];

            foreach ($this->details_ AS $detail)
            {
                if (!isset($detail['phrase']))
                {
                    continue;
                }

                $output[] = \XF::phrase($detail['phrase'], isset($detail['data']) ? $detail['data'] : []);
            }

            return $output;
        }

        return parent::getDetails();
    }
}