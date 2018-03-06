<?php

namespace SV\WarningImprovements;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;
use XF\Entity\User;

class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    public function installStep1()
    {
    }

    public function uninstallStep1()
    {
    }

    public function upgrade2000070Step1()
    {
        $this->renameOption('sv_contactusthread_node','svContactUsNode');
        $this->renameOption('sv_contactusthread_ratelimit','svContactUsThreadRateLimit');
        $this->renameOption('sv_contactusthread_spamtriggerlogdays','svContactUsSpamTriggerLogDays');
        $this->renameOption('sv_contactusthread_spamtriggerloglimit','svContactUsSpamTriggerLogLimit');
        $this->renameOption('sv_banned_user_can_use_contactus_form','svContactUsAllowBanned');
        $this->renameOption('sv_discardcontactusmessage','svContactUsDiscardDiscourage');
        $this->renameOption('sv_contactus_spamCheck','svContactSpamCheck');
    }

    protected function renameOption($old, $new)
    {
        /** @var \XF\Entity\Option $optionOld */
        $optionOld = \XF::finder('XF:Option')->whereId($old)->fetchOne();
        $optionNew = \XF::finder('XF:Option')->whereId($new)->fetchOne();
        if ($optionOld && !$optionNew)
        {
            $optionOld->option_id = $new;
            $optionOld->save();
        }
    }
}
