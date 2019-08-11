<?php

namespace SV\ContactUsThread;

/**
 * Class Globals
 *
 * @package SV\ContactUsThread
 */
class Globals
{
    /** @var boolean */
    public static $doLoginRedirect = null;

    /** @var boolean */
    public static $spamTriggerDetailsAsArray = false;

    public static function doLoginRedirect()
    {
        if (self::$doLoginRedirect == null)
        {
            $addOnsCache = \XF::app()->container('addon.cache');

            self::$doLoginRedirect = isset($addOnsCache['SV/SignupAbuseBlocking']);
        }

        return self::$doLoginRedirect;
    }
}