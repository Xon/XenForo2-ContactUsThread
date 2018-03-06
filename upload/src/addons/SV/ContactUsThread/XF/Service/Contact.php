<?php

namespace SV\ContactUsThread\XF\Service;

class Contact extends XFCP_Contact
{
    public function validate(&$errors = [])
    {
        if ($this->fromUser === null && $this->fromName)
        {
            $validator = $this->app->validator('Username');
            $validator->setOption('check_unique', false);
            $validator->setOption('force_next_validator_request', true);
            if (!$validator->isValid($this->fromName, $errorKey))
            {
                $errors['username'] = $validator->getPrintableErrorValue($errorKey);
            }
        }

        return parent::validate($errors);
    }

    public function send()
    {
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

            // todo: spam checks

            /** @var \XF\Repository\User $userRepo */
            $userRepo = $this->repository('XF:User');
            $visitor = \XF::visitor();

            $title = $input['subject'];
            if ($visitor->user_id)
            {
                $user = $visitor;

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
            });
            $creator->sendNotifications();
        }

        parent::send();
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