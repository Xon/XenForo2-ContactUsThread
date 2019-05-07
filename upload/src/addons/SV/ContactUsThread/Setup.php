<?php

namespace SV\ContactUsThread;

use SV\Utils\InstallerHelper;
use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;

class Setup extends AbstractSetup
{
    // from https://github.com/Xon/XenForo2-Utils cloned to src/addons/SV/Utils
    use InstallerHelper;
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    public function installStep1()
    {
        $sm = $this->schemaManager();

        foreach ($this->getTables() as $tableName => $callback)
        {
            $sm->createTable($tableName, $callback);
            $sm->alterTable($tableName, $callback);
        }
    }

    public function uninstallStep1()
    {
        $sm = $this->schemaManager();

        foreach ($this->getTables() as $tableName => $callback)
        {
            $sm->dropTable($tableName);
        }
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

    public function upgrade2020000Step1()
    {
        $this->installStep1();
    }

    protected function getTables()
    {
        $tables = [];
        $tables['xf_sv_ban_email_contact_us'] = function($table)
        {
            /** @var Create|Alter $table */
            $this->addOrChangeColumn($table,'banned_email', 'varchar', 120);
            $this->addOrChangeColumn($table,'create_user_id', 'int')->setDefault(0);
            $this->addOrChangeColumn($table,'create_date', 'int')->setDefault(0);
            $this->addOrChangeColumn($table,'reason', 'varchar', 255)->setDefault('');
            $this->addOrChangeColumn($table,'last_triggered_date', 'int')->setDefault(0);
            $table->addPrimaryKey('banned_email');
            $table->addKey('create_date');
        };

        return $tables;
    }
}
