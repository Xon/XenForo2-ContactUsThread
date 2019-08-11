<?php

namespace SV\ContactUsThread\XF\Service;

use XF\Mvc\Entity\AbstractCollection;
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
        if (!empty($options->svContactUsDiscardDiscourage) && $this->isDiscouraged)
        {
            return;
        }
        else if ($this->fromEmail)
        {
            if (!empty($options->svContactUsSilentDiscardBannedEmails))
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
        if (empty($options->svContactUsNode))
        {
            return;
        }
        /** @var \XF\Entity\Forum $forum */
        $forum = $this->em()->find('XF:Forum', $options->svContactUsNode);

        if ($forum)
        {
            $this->svPostThreadToForum($forum);
        }
    }

    protected function getThreadPhraseInputs(/** @noinspection PhpUnusedParameterInspection */ \XF\Entity\Forum  $forum, \XF\Entity\User  $user)
    {
        $input = [
            'user'              => $user,
            'username'          => $this->fromName,
            'email'             => $this->fromEmail,
            'subject'           => $this->subject,
            'message'           => $this->message,
            'ip'                => $this->fromIp,
            'spam_trigger_logs' => $this->getSpamTriggerLogs(),
        ];

        if (!$user->user_id)
        {
            $addOnsCache = \XF::app()->container('addon.cache');
            if (isset($addOnsCache['SV/SignupAbuseBlocking']))
            {
                $options = \XF::options();
                $oldValue = $options->svSockSignupCookieFormat;
                $options->svSockSignupCookieFormat = [];
                try
                {
                    /** @var \SV\SignupAbuseBlocking\Repository\MultipleAccount $multipleAccountRepo */
                    $multipleAccountRepo = $this->repository('SV\SignupAbuseBlocking:MultipleAccount');
                    $receivedToken = $multipleAccountRepo->getCookieValue('contact_us');
                    if ($receivedToken)
                    {
                        /** @var \SV\SignupAbuseBlocking\Entity\Token $tokenEntity */
                        $tokenEntity = $multipleAccountRepo->getTokenFromCookie($receivedToken);
                        if ($tokenEntity)
                        {
                            $multiAccount = $tokenEntity->User;
                            if ($multiAccount && $multiAccount->user_id != $user->user_id)
                            {
                                // multi-account detected a user, when the user isn't logged in
                                $input['multiAccount'] = $multiAccount;
                            }
                        }
                    }
                }
                finally
                {
                    $options->svSockSignupCookieFormat = $oldValue;
                }
            }
        }

        return $input;
    }

    /**
     * @return AbstractCollection|null
     */
    protected function getSpamTriggerLogs()
    {
        $options = $this->app->options();

        $spamTriggerLogDays = intval($options->svContactUsSpamTriggerLogDays);
        $spamTriggerLogLimit = intval($options->svContactUsSpamTriggerLogLimit);

        if (!$spamTriggerLogDays || !$spamTriggerLogLimit)
        {
            return null;
        }
        $binaryIp = Ip::convertIpStringToBinary($this->fromIp);
        $date = \XF::$time - ($spamTriggerLogDays * 86400);

        $logs = null;
        $addOnsCache = \XF::app()->container('addon.cache');
        if (isset($addOnsCache['SV/SignupAbuseBlocking']))
        {
            /** @var \SV\SignupAbuseBlocking\Finder\UserRegistrationLog $finder */
            $finder = \XF::finder('SV\SignupAbuseBlocking:UserRegistrationLog')
                         ->with('User')
                         ->setDefaultOrder('log_date', 'DESC')
                         ->where('log_date', '>', $date);
            $orConditions = [];
            $orConditions[] = ['User.email', '=', $this->fromEmail];
            $orConditions[] = ['details', 'like', '%' . $this->fromEmail . '%'];

            if ($binaryIp)
            {
                $orConditions[] = ['ip_address', '=', $binaryIp];
            }

            $finder->whereOr($orConditions);

            $logs = $finder->fetch();
        }
        // try normal spam trigger log as well
        if (!$logs || !$logs->count())
        {
            /** @var \XF\Finder\SpamTriggerLog $finder */
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

            $logs = $finder->fetch();
        }

        return $logs;
    }

    protected function svPostThreadToForum(\XF\Entity\Forum  $forum)
    {
        if ($this->fromUser)
        {
            $user = $this->fromUser;
        }
        else
        {
            /** @var \XF\Repository\User $userRepo */
            $userRepo = $this->repository('XF:User');
            $user = $userRepo->getGuestUser($this->fromName);
            $user->setAsSaved('email', $this->fromEmail);
        }
        $input = $this->getThreadPhraseInputs($forum, $user);

        $title = $input['subject'];
        $message = \XF::app()->templater()->renderTemplate('public:svContactUs_message', $input);

        $creator = \XF::asVisitor($user, function () use ($forum, $title, $message) {
            /** @var \XF\Service\Thread\Creator $creator */
            $creator = $this->service('XF:Thread\Creator', $forum);
            $creator->setPerformValidations(false);
            $creator->setContent($title, $message);
            $defaultPrefix = isset($forum->sv_default_prefix_ids) ? $forum->sv_default_prefix_ids : $forum->default_prefix_id;
            if ($defaultPrefix)
            {
                $creator->setPrefix($defaultPrefix);
            }
            $creator->save();

            return $creator;
        });

        $creator->sendNotifications();
    }
}