<?php

namespace SV\ContactUsThread\Cli\Command\Seed;

if (!\class_exists('TickTackk\Seeder\Cli\Command\Seed\AbstractSeedCommand'))
{
    return;
}

use Symfony\Component\Console\Input\InputInterface;
use TickTackk\Seeder\Cli\Command\Seed\AbstractSeedCommand;

/**
 * @since 2.3.6
 */
class SeedSvContactUsEmailBan extends AbstractSeedCommand
{
    protected function getSeedName(): string
    {
        return 'sv-contact-us-email-ban';
    }

    protected function getContentTypePlural(InputInterface $input = null): string
    {
        return 'Contact Us Email bans by Xon';
    }
}