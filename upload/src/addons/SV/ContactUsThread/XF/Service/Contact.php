<?php

namespace SV\ContactUsThread\XF\Service;

class Contact extends XFCP_Contact
{
    /** @var bool  */
    protected $doneSpamChecks = false;
    /** @var string[] */
    protected $errors = [];

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

        if ($this->fromUser)
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

    public function validate(&$errors = [])
    {
        /** @noinspection PhpUnusedLocalVariableInspection */
        $hasErrors = parent::validate($errors);

        if ($this->fromUser === null && $this->fromName)
        {
            /** @var \XF\Validator\Username $validator */
            $validator = $this->app->validator('Username');
            $validator->setOption('check_unique', false);
            $validator->setOption('force_next_validator_request', true);
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
        return !count($errors);
    }

    public function send()
    {
        parent::send();

        $options = $this->app->options();
        /** @var \XF\Entity\Forum $forum */
        $forum = $this->em()->find('XF:Forum', $options->svContactUsNode);

        if ($forum)
        {
            $input = [
                'email'   => $this->fromEmail,
                'subject' => $this->subject,
                'message' => $this->message,
                'ip'      => $this->fromIp
            ];

            /** @var \XF\Repository\User $userRepo */
            $userRepo = $this->repository('XF:User');

            $title = $input['subject'];
            if ($this->fromUser)
            {
                $user = $this->fromUser;

                $message = \XF::phrase('ContactUs_Message_User', $input);
            }
            else
            {
                $username = $this->fromName;
                $user = $userRepo->getGuestUser($username);

                $message = \XF::phrase('ContactUs_Message_Guest', $input);
            }

            $creator = \XF::asVisitor($user, function () use ($forum, $title, $message) {
                /** @var \XF\Service\Thread\Creator $creator */
                $creator = $this->service('XF:Thread\Creator', $forum);
                $creator->setContent($title, $message);
                $creator->setPrefix($forum->default_prefix_id);
                $creator->save();

                return $creator;
            });
            $creator->sendNotifications();
        }
    }

    protected function _formatLogsForDisplay(array $logs)
    {
        if (!empty($logs))
        {
            $logOutput = "[LIST]\n";

            foreach ($logs as $log)
            {
                $time = \XF::language()->time($log['log_date'], 'absolute');
                $logOutput .= "[*]{$time}: ";

                if ($log['username'])
                {
                    $logOutput .= "@{$log['username']} ";
                }
                else
                {
                    $logOutput .= \XF::phrase('unknown_account') . ' ';
                }

                $logOutput .= ' - ';

                if ($log['result'] == 'denied')
                {
                    $result = \XF::phrase('rejected');
                }
                else if ($log['result'] == 'moderated')
                {
                    $result = \XF::phrase('moderated');
                }
                else
                {
                    $result = $log['result'];
                }

                $logOutput .= $result;

                foreach ($log['detailsPrintable'] as $detail)
                {
                    $logOutput .= " ({$detail})";
                }

                $logOutput .= "\n";
            }

            $logOutput .= '[/LIST]';
        }
        else
        {
            $logOutput = \XF::phrase(
                'sv_contactusthread_no_matching_spam_trigger_logs'
            );
        }

        return $logOutput;
    }
}