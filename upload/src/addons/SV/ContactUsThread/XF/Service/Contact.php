<?php

namespace SV\ContactUsThread\XF\Service;

use XF\Entity\SpamTriggerLog;
use XF\Util\Ip;

class Contact extends XFCP_Contact
{
    /** @var bool */
    protected $doneSpamChecks = false;
    /** @var string[] */
    protected $errors = [];
    /** @var bool */
    protected $isDiscouraged = false;

    public function checkForSpam()
    {
        if ($this->doneSpamChecks)
        {
            return;
        }
        // for some reason validate gets called twice...
        $this->doneSpamChecks = true;
        /** @var \XF\Repository\User $userRepo */
        $userRepo = $this->repository('XF:User');

        if (!$this->fromUser)
        {
            $fromUser = $userRepo->getGuestUser($this->fromName);
            $fromUser->setAsSaved('email', $this->fromEmail);
        }
        else
        {
            $fromUser = $this->fromUser;
        }

        $content = $this->subject . "\n" . $this->message;

        $checker = $this->app->spam()->contentChecker();
        $checker->check($fromUser, $content, [
            'content_type' => 'post' // see \XF\Spam\Checker\Akismet for content types
        ]);

        $decision = $checker->getFinalDecision();
        switch ($decision)
        {
            case 'moderated':
            case 'denied':
                $checker->logSpamTrigger('contact_us', $fromUser->user_id);
                $this->errors['message'] = \XF::phrase('your_content_cannot_be_submitted_try_later');
                break;
        }
    }

    protected $validated2;

    public function validate(&$errors = [])
    {
        $errors = [];

        /** @noinspection PhpUnusedLocalVariableInspection */
        $hasErrors = parent::validate($errors);

        if (!$this->validated2)
        {

            if ($this->fromUser === null && $this->fromName)
            {
                /** @var \XF\Validator\Username $validator */
                $validator = $this->app->validator('Username');
                $validator->setOption('admin_edit', true);
                $validator->setOption('check_unique', false);
                if (!$validator->isValid($this->fromName, $errorKey))
                {
                    $errors['username'] = $validator->getPrintableErrorValue($errorKey);
                }
            }

            $options = $this->app->options();
            if ($options->svContactSpamCheck && !$this->doneSpamChecks)
            {
                $this->checkForSpam();
            }

            $errors = array_merge($errors, $this->errors);
            $this->validated2 = true;
        }

        return !count($errors);
    }

    /**
     * @param bool $isDiscouraged
     */
    public function setDiscouraged($isDiscouraged)
    {
        $this->isDiscouraged = $isDiscouraged;
    }

    public function send()
    {
        $options = $this->app->options();
        if ($options->svContactUsDiscardDiscourage && $this->isDiscouraged)
        {
            return;
        }
        else if ($this->fromEmail)
        {
            if ($options->svContactUsSilentDiscardBannedEmails)
            {
                /** @var \XF\Validator\Email $validator */
                $validator = $this->app->validator('Email');
                $validator->setOption('banned', $this->app->container('bannedEmails'));

                $email = $validator->coerceValue($this->fromEmail);
                if (!$validator->isValid($email))
                {

                    return;
                }
            }

            /** @var \SV\ContactUsThread\Repository\Banning $shadowBannedRepo */
            $shadowBannedRepo = $this->repository('SV\ContactUsThread:Banning');
            $shadowBannedEmails = $shadowBannedRepo->findEmailBans()->fetchColumns('banned_email');

            /** @var \SV\ContactUsThread\Entity\BanEmail $bannedEmail */
            foreach ($shadowBannedEmails AS $bannedEmail)
            {
                $bannedEmail = $rawValue = $bannedEmail['banned_email'];
                $bannedEmail = str_replace('\\*', '(.*)', preg_quote($bannedEmail, '/'));
                if (preg_match('/^' . $bannedEmail . '$/i', $this->fromEmail))
                {
                    \XF::db()->query('update xf_sv_ban_email_contact_us set last_triggered_date = unix_timestamp() where banned_email = ?', $rawValue);
                    return;
                }
            }
        }

        parent::send();

        $options = $this->app->options();
        /** @var \XF\Entity\Forum $forum */
        $forum = $this->em()->find('XF:Forum', $options->svContactUsNode);

        if ($forum)
        {
            $input = [
                'username' => $this->fromName,
                'email'    => $this->fromEmail,
                'subject'  => $this->subject,
                'message'  => $this->message,
                'ip'       => $this->fromIp,
            ];

            $spamTriggerLogDays = intval($options->svContactUsSpamTriggerLogDays);
            $spamTriggerLogLimit = intval($options->svContactUsSpamTriggerLogLimit);

            if ($spamTriggerLogDays && $spamTriggerLogLimit)
            {
                $binaryIp = Ip::convertIpStringToBinary($this->fromIp);

                $date = \XF::$time - ($spamTriggerLogDays * 86400);
                $finder = $this->finder('XF:SpamTriggerLog')
                               ->with('User')
                               ->setDefaultOrder('log_date', 'DESC')
                               ->where('content_type', '=', 'user')
                               ->where('log_date', '>', $date);

                $orConditions = [];
                $orConditions[] = ['User.email', '=', $this->fromEmail];
                $orConditions[] = ['details', 'like', '%' . $this->fromEmail . '%'];

                if ($binaryIp)
                {
                    $orConditions[] = ['ip_address', '=', $binaryIp];
                }

                $finder->whereOr($orConditions);

                /** @var \XF\Entity\SpamTriggerLog[] $logs */
                $logs = $finder->fetch()->toArray();
                $input['spam_trigger_logs'] = $this->_formatLogsForDisplay($logs);
            }
            else
            {
                $input['spam_trigger_logs'] = '';
            }

            /** @var \XF\Repository\User $userRepo */
            $userRepo = $this->repository('XF:User');

            $title = $input['subject'];
            if ($this->fromUser)
            {
                $user = $this->fromUser;

                $message = \XF::phrase('ContactUs_Message_User', $input)->render('raw');
            }
            else
            {
                $username = $this->fromName;
                $user = $userRepo->getGuestUser($username);

                $message = \XF::phrase('ContactUs_Message_Guest', $input)->render('raw');
            }

            $creator = \XF::asVisitor($user, function () use ($forum, $title, $message) {
                $creator = $this->setupThreadCreate($forum);
                $creator->setContent($title, $message);

                $creator->save();

                return $creator;
            });

            $this->finalizeThreadCreate($creator);
        }
    }

    protected function setupThreadCreate(\XF\Entity\Forum $forum)
    {
        /** @var \XF\Service\Thread\Creator $creator */
        $creator = $this->service('XF:Thread\Creator', $forum);
        $creator->setPerformValidations(false);

        $defaultPrefix = isset($forum->sv_default_prefix_ids) ? $forum->sv_default_prefix_ids : $forum->default_prefix_id;
        if ($defaultPrefix)
        {
                $creator->setPrefix($defaultPrefix);
        }

        return $creator;
    }

    protected function finalizeThreadCreate(\XF\Service\Thread\Creator $creator)
    {
        $creator->sendNotifications();
    }

    /**
     * @param SpamTriggerLog[] $logs
     * @return string
     */
    protected function _formatLogsForDisplay(array $logs)
    {
        if (!empty($logs))
        {
            $logOutput = "[LIST]\n";

            foreach ($logs as $log)
            {
                $time = \XF::language()->time($log->log_date, 'absolute');
                $logOutput .= "[*]{$time}: ";

                if ($log->User)
                {
                    $logOutput .= "@{$log->User->username} ";
                }
                else
                {
                    $logOutput .= \XF::phrase('unknown_account') . ' ';
                }

                $logOutput .= ' - ';

                if ($log->result == 'denied')
                {
                    $result = \XF::phrase('rejected');
                }
                else if ($log->result == 'moderated')
                {
                    $result = \XF::phrase('moderated');
                }
                else
                {
                    $result = $log['result'];
                }

                $logOutput .= $result;

                $details = $log->details;
                if (is_array($details))
                {
                    foreach ($details as $detail)
                    {
                        $logOutput .= " ({$detail})";
                    }
                }

                $logOutput .= "\n";
            }

            $logOutput .= '[/LIST]';
        }
        else
        {
            $logOutput = \XF::phrase('sv_contactusthread_no_matching_spam_trigger_logs');
        }

        return $logOutput;
    }
}