<?php

namespace SV\ContactUsThread\Service\BannedEmails;

use XF\Service\AbstractXmlImport;

class Import extends AbstractXmlImport
{
    public function import(\SimpleXMLElement $xml)
    {
        /** @var \SV\ContactUsThread\Repository\Banning $repo */
        $repo = $this->repository('SV\ContactUsThread:Banning');

        $bannedEmailsCache = $repo->findEmailBans()->fetch()->keys();
        $bannedEmailsCache = array_map('strtolower', $bannedEmailsCache);

        $entries = $xml->entry;
        foreach ($entries AS $entry)
        {
            if (in_array(strtolower((string)$entry['banned_email']), $bannedEmailsCache))
            {
                // already exists
                continue;
            }

            $repo->banEmail(
                (string)$entry['banned_email'],
                \XF\Util\Xml::processSimpleXmlCdata($entry->reason)
            );
        }
    }
}