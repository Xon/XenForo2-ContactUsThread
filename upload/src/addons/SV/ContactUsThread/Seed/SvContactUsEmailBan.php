<?php

namespace SV\ContactUsThread\Seed;

use TickTackk\Seeder\Seed\AbstractSeed;
use SV\ContactUsThread\Entity\BanEmail as SvBanEmailEntity;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Repository;
use SV\ContactUsThread\Repository\Banning as SvBanningRepo;

/**
 * @since 2.3.6
 */
class SvContactUsEmailBan extends AbstractSeed
{
    protected function getEmail() : string
    {
        $faker = $this->faker();

        return $faker->word . $faker->email;
    }

    protected function getReason() : string
    {
        $faker = $this->faker();
        if (!$faker->boolean)
        {
            return '';
        }

        $reason = $faker->words;
        if (\is_array($reason))
        {
            $reason = \implode(' ', $reason);
        }

        return $reason;
    }

    public function getCreateDate() : int
    {
        return $this->faker()->dateTimeThisYear()->getTimestamp();
    }

    public function getLastTriggerDate() : int
    {
        $faker = $this->faker();
        if (!$faker->boolean)
        {
            return 0;
        }

        return $this->faker()->dateTimeThisYear()->getTimestamp();
    }

    protected function seed(array $params = []): bool
    {
        $email = $this->getEmail();
        $reason = $this->getReason();

        if (!$this->getSvBanningRepo()->banEmail($email, $reason))
        {
            return false;
        }

        $emailBan = $this->getSvEmailByEmailAndReason($email, $reason);
        if ($emailBan)
        {
            $createDate = $this->getCreateDate();
            $lastTriggerDate = $this->getLastTriggerDate();

            $emailBan->fastUpdate([
                'create_date' => ($createDate > $lastTriggerDate) ? $lastTriggerDate : $createDate,
                'last_triggered_date' => ($createDate > $lastTriggerDate) ? $createDate : $lastTriggerDate
            ]);
        }

        return true;
    }

    /**
     * @param string $email
     * @param string $reason
     *
     * @return Entity|SvBanEmailEntity|null
     */
    protected function getSvEmailByEmailAndReason(string $email, string $reason)
    {
        return $this->finder('SV\ContactUsThread:BanEmail')
            ->where('banned_email', $email)
            ->where('reason', $reason)
            ->fetchOne();
    }

    /**
     * @return Repository|SvBanningRepo
     */
    protected function getSvBanningRepo() : SvBanningRepo
    {
        return $this->repository('SV\ContactUsThread:Banning');
    }
}