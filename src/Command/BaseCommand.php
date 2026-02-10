<?php

declare(strict_types=1);

/**
 * This file is part of the  AbraFlexi CLI package.
 *
 * (c) Vítězslav Dvořák <https://vitexsoftware.cz/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace VitexSoftware\AbraflexiCli\Command;

use AbraFlexi\RO;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class BaseCommand extends Command
{
    protected RO $connection;

    // Removed empty configure() to avoid potential issues with child commands
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $config = [
            'url' => getenv('ABRAFLEXI_URL'),
            'user' => getenv('ABRAFLEXI_USER'),
            'password' => getenv('ABRAFLEXI_PASSWORD'),
            'company' => getenv('ABRAFLEXI_COMPANY'),
        ];

        // Basic connection check/setup could go here if needed
    }

    protected function configure(): void
    {
        $this->setName('base-command') // A base command typically doesn't have a specific name, but added as per instruction.
            ->setDescription('Base command for AbraFlexi CLI tools');
    }

    protected function getAbraFlexiOptions(): array
    {
        $options = [
            'verify' => false,
        ];

        if (getenv('ABRAFLEXI_URL')) {
            $options['url'] = getenv('ABRAFLEXI_URL');
        }

        if (getenv('ABRAFLEXI_LOGIN')) {
            $options['user'] = getenv('ABRAFLEXI_LOGIN');
        } elseif (getenv('ABRAFLEXI_USER')) {
            $options['user'] = getenv('ABRAFLEXI_USER');
        }

        if (getenv('ABRAFLEXI_PASSWORD')) {
            $options['password'] = getenv('ABRAFLEXI_PASSWORD');
        }

        if (getenv('ABRAFLEXI_COMPANY')) {
            $options['company'] = getenv('ABRAFLEXI_COMPANY');
        }

        return $options;
    }
}
