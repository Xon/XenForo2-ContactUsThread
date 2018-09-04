<?php

namespace SV\ContactUsThread;

use SV\Utils\InstallerHelper;
use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;

class Setup extends AbstractSetup
{
    // from https://github.com/Xon/XenForo2-Utils cloned to src/addons/SV/Utils
    use InstallerHelper;
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    public function installStep1()
    {
    }

    public function uninstallStep1()
    {
    }

    public function upgrade2000100Step1()
    {
        $this->renameOption('sv_contactusthread_node','svContactUsNode');
        $this->renameOption('sv_contactusthread_ratelimit','svContactUsThreadRateLimit');
        $this->renameOption('sv_contactusthread_spamtriggerlogdays','svContactUsSpamTriggerLogDays');
        $this->renameOption('sv_contactusthread_spamtriggerloglimit','svContactUsSpamTriggerLogLimit');
        $this->renameOption('sv_banned_user_can_use_contactus_form','svContactUsAllowBanned');
        $this->renameOption('sv_discardcontactusmessage','svContactUsDiscardDiscourage');
        $this->renameOption('sv_contactus_spamCheck','svContactSpamCheck');
    }
}
