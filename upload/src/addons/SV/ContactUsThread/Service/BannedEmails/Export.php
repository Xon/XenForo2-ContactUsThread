<?php

namespace SV\ContactUsThread\Service\BannedEmails;

use XF\Entity\BanEmail;
use XF\Mvc\Entity\Entity;
use XF\Service\AbstractXmlExport;

class Export extends AbstractXmlExport
{
    public function getRootName()
    {
        return 'banned_emails';
    }

    protected function exportEntry(Entity $entity, \DOMElement $node)
    {
        /** @var BanEmail $entity */
        $reasonNode = $node->ownerDocument->createElement('reason');
        $this->exportCdata($reasonNode, $entity->reason);
        $node->appendChild($reasonNode);
    }

    protected function getAttributes()
    {
        return [
            'banned_email'
        ];
    }
}