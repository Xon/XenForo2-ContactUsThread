<?php

namespace SV\ContactUsThread\XF\Admin\Controller;

use XF\Entity\User as UserEntity;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Finder;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Redirect as RedirectReply;
use XF\Mvc\Reply\View as ViewReply;
use XF\Mvc\Reply\Exception as ExceptionReply;

/**
 * Extends \XF\Admin\Controller\Banning
 */
class Banning extends XFCP_Banning
{
    protected function preDispatchController($action, ParameterBag $params)
    {
        parent::preDispatchController($action, $params);

        if (preg_match('/^emailscontact/i', $action))
        {
            $this->setSectionContext('bannedEmailsContactUs');
        }
    }

    /**
     * @since 2.3.6
     *
     * @param string|array $usernames
     *
     * @return AbstractCollection|Entity[]|UserEntity[]
     */
    protected function getUsersByUsernameForSvEmailsContact($usernames) : AbstractCollection
    {
        if (\is_string($usernames))
        {
            $usernames = \explode(',', $usernames);
        }
        $usernames = \array_filter($usernames, 'trim');

        return $this->finder('XF:User')->where('username', $usernames)->fetch();
    }

    /**
     * @since 2.3.6
     *
     * @param array $userIds
     *
     * @return AbstractCollection|Entity[]|UserEntity[]
     */
    protected function getUsersByIdsForSvEmailsContact(array $userIds) : AbstractCollection
    {
        return $this->finder('XF:User')->where('user_id', $userIds)->fetch();
    }

    /**
     * @since 2.3.6
     *
     * @param string $creatorsFilter
     *
     * @return array
     */
    protected function getFilterInputForSvEmailsContact(string &$creatorsFilter = '') : array
    {
        $filters = [];

        $creators = $this->em()->getEmptyCollection();

        $input = $this->filter([
            'banned_email' => 'str',
            'creators' => 'str',
            'create_user_ids' => 'array-uint',
            'reason' => 'str',
            'create_date_start' => 'datetime'
        ]);

        if (\strlen($input['banned_email']))
        {
            $filters['banned_email'] = $input['banned_email'];
        }

        if ($input['create_user_ids'])
        {
            $filters['create_user_ids'] = $input['create_user_ids'];
            if (\count($filters['create_user_ids']))
            {
                $creators = $this->getUsersByIdsForSvEmailsContact($filters['create_user_ids']);
            }
        }
        else if ($input['creators'])
        {
            $creators = $this->getUsersByUsernameForSvEmailsContact($input['creators']);
            if ($creators->count())
            {
                $filters['create_user_ids'] = $creators->keys();
            }
        }

        if ($creators->count())
        {
            $creatorsFilter = \implode(', ', \array_keys($creators->groupBy('username')));
            $creatorsFilter .= ', ';
        }

        if (\strlen($input['reason']))
        {
            $filters['reason'] = $input['reason'];
        }

        if (!empty($input['create_date_start']))
        {
            $filters['create_date_start'] = $input['create_date_start'];
        }

        return $filters;
    }

    /**
     * @since 2.3.6
     *
     * @param Finder $finder
     * @param array $filters
     */
    protected function applyFiltersForSvEmailsContact(Finder $finder, array $filters)
    {
        if (\array_key_exists('banned_email', $filters))
        {
            $finder->where(
                'banned_email',
                'LIKE',
                $finder->escapeLike($filters['banned_email'], '%?%')
            );
        }

        if (\array_key_exists('create_user_ids', $filters))
        {
            $finder->where('create_user_id', $filters['create_user_ids']);
        }

        if (\array_key_exists('reason', $filters))
        {
            $finder->where(
                'reason',
                'LIKE',
                $finder->escapeLike($filters['reason'], '%?%')
            );
        }

        if (\array_key_exists('create_date_start', $filters))
        {
            $finder->where(
                'create_date',
                '>=',
                $filters['create_date_start']
            );
        }
    }

    /**
     * @version 2.3.6
     *
     * @return AbstractReply|RedirectReply|ViewReply
     *
     * @throws ExceptionReply
     */
    public function actionEmailsContact()
    {
        $creatorsFilter = '';
        $filters = $this->getFilterInputForSvEmailsContact($creatorsFilter);
        if ($this->filter('apply', 'bool'))
        {
            return $this->redirect($this->buildLink('banning/emails-contact', null, $filters));
        }

        $page = $this->filterPage();
        $perPage = 20;

        $order = $this->filter('order', 'str', 'create_date');
        $direction = $this->filter('direction', 'str', 'desc');

        $orderFields = [
            [$order, $direction]
        ];
        if ($order !== 'banned_email')
        {
            // If not already set, add this as a secondary sort because
            // majority of fields may be blank (especially legacy data)
            $orderFields[] = ['banned_email', 'asc'];
        }

        $emailBanFinder = $this->getSvBanningRepo()->findEmailBans()
                               ->with('User')
                               ->order($orderFields);

        $this->applyFiltersForSvEmailsContact($emailBanFinder, $filters);

        $emailBanFinder->limitByPage($page, $perPage);
        $total = $emailBanFinder->total();

        $this->assertValidPage($page, $perPage, $total, 'banning/emails-contact');

        $viewParams = [
            'emailBans' => $emailBanFinder->fetch(),

            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'filters' => $filters,
            'creatorsFilter' => $creatorsFilter,

            'order' => $order,
            'direction' => $direction,
            'newEmail' => $this->em()->create('SV\ContactUsThread:BanEmail')
        ];
        return $this->view('XF:Banning\Email\Listing', 'sv_ban_contact_email_list', $viewParams);
    }

    public function actionEmailsContactAdd()
    {
        $this->assertPostOnly();

        $this->getSvBanningRepo()->banEmail(
            $this->filter('email', 'str'),
            $this->filter('reason', 'str')
        );
        return $this->redirect($this->buildLink('banning/emails-contact'));
    }

    public function actionEmailsContactDelete()
    {
        $this->assertPostOnly();

        $deletes = $this->filter('delete', 'array-str');

        $emailBans = $this->em()->findByIds('SV\ContactUsThread:BanEmail', $deletes);
        foreach ($emailBans AS $emailBan)
        {
            $emailBan->delete();
        }

        return $this->redirect($this->buildLink('banning/emails-contact'));
    }

    public function actionEmailsContactExport()
    {
        $bannedEmails = $this->getSvBanningRepo()->findEmailBans();
        /** @var \XF\ControllerPlugin\Xml $xmlPlugin */
        $xmlPlugin = $this->plugin('XF:Xml');

        return $xmlPlugin->actionExport($bannedEmails, 'SV\ContactUsThread:BannedEmails\Export');
    }

    public function actionEmailsContactImport()
    {
        /** @var \XF\ControllerPlugin\Xml $xmlPlugin */
        $xmlPlugin = $this->plugin('XF:Xml');

        return $xmlPlugin->actionImport('banning/emails-contact', 'banned_emails', 'SV\ContactUsThread:BannedEmails\Import');
    }

    /**
     * @return \XF\Mvc\Entity\Repository|\SV\ContactUsThread\Repository\Banning
     */
    protected function getSvBanningRepo()
    {
        return $this->repository('SV\ContactUsThread:Banning');
    }
}