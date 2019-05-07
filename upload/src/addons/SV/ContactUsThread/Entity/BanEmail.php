<?php

namespace SV\ContactUsThread\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property string banned_email
 * @property int create_user_id
 * @property int create_date
 * @property string reason
 * @property int last_triggered_date
 *
 * RELATIONS
 * @property \XF\Entity\User User
 */
class BanEmail extends Entity
{
    protected function verifyBannedEmail(&$email)
    {
        if ($email == '*' || $email === '')
        {
            $this->error(\XF::phrase('you_must_enter_at_least_one_non_wildcard_character'), 'banned_email');

            return false;
        }

        if (strpos($email, '*') === false)
        {
            if (strpos($email, '@') === false)
            {
                $email = '*' . $email;
            }
            if (strpos($email, '.') === false)
            {
                $email .= '*';
            }
        }

        if ($email[0] == '@')
        {
            $email = '*' . $email;
        }

        $lastChar = substr($email, -1);
        if ($lastChar == '.' || $lastChar == '@')
        {
            $email .= '*';
        }

        $atPos = strpos($email, '@');
        if ($atPos !== false && strpos($email, '.', $atPos) === false && strpos($email, '*', $atPos) === false)
        {
            $email .= '*';
        }

        if ($email == '*@*' || $email == '*.*')
        {
            $this->error(\XF::phrase('this_would_ban_all_email_addresses'), 'banned_email');

            return false;
        }

        $email = preg_replace('/\*{2,}/', '*', $email);

        return true;
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_sv_ban_email_contact_us';
        $structure->shortName = 'SV\ContactUsThread:BanEmail';
        $structure->primaryKey = 'banned_email';
        $structure->columns = [
            'banned_email'        => ['type' => self::STR, 'required' => true, 'maxLength' => 120],
            'create_user_id'      => ['type' => self::UINT, 'required' => true],
            'create_date'         => ['type' => self::UINT, 'default' => \XF::$time],
            'reason'              => ['type' => self::STR, 'maxLength' => 255, 'default' => ''],
            'last_triggered_date' => ['type' => self::UINT, 'default' => 0]
        ];
        $structure->getters = [];
        $structure->relations = [
            'User' => [
                'entity'     => 'XF:User',
                'type'       => self::TO_ONE,
                'conditions' => [
                    ['user_id', '=', '$create_user_id']
                ],
                'primary'    => true
            ]
        ];

        return $structure;
    }
}