<?php

namespace SV\ContactUsThread\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class Banning extends Repository
{
    /**
     * @return Finder
     */
    public function findEmailBans()
    {
        return $this->finder('SV\ContactUsThread:BanEmail')
                    ->setDefaultOrder('banned_email', 'asc');
    }

    /**
     * @param string               $email
     * @param string               $reason
     * @param \XF\Entity\User|null $user
     * @return bool
     * @throws \XF\PrintableException
     */
    public function banEmail($email, $reason = '', \XF\Entity\User $user = null)
    {
        $user = $user ?: \XF::visitor();

        /** @var \SV\ContactUsThread\Entity\BanEmail $emailBan */
        $emailBan = $this->em->create('SV\ContactUsThread:BanEmail');
        $emailBan->banned_email = $email;
        $emailBan->reason = $reason;
        $emailBan->create_user_id = $user->user_id;

        return $emailBan->save();
    }
}