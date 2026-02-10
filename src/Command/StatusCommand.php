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

use AbraFlexi\Company;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StatusCommand extends BaseCommand
{
    protected static $defaultName = 'status';

    protected function configure(): void
    {
        $this->setName('status')
            ->setDescription('Show information about configured company and server state');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $options = $this->getAbraFlexiOptions();
        $output->writeln('<info>Configured AbraFlexi Connection:</info>');
        $output->writeln('URL: ' . ($options['url'] ?? 'Not set'));
        $output->writeln('User: ' . ($options['user'] ?? 'Not set'));
        $output->writeln('Company: ' . ($options['company'] ?? 'Not set'));

        if (empty($options['url']) || empty($options['user']) || empty($options['password']) || empty($options['company'])) {
            $output->writeln('<error>Some AbraFlexi connection parameters are missing.</error>');
            return Command::FAILURE;
        }

        $output->writeln('');
        $output->writeln('<info>Checking server and company state...</info>');
        try {
            $companyClient = new Company($options['company'], $options);
            $companyInfo = $companyClient->getData();
            if (isset($companyInfo['nazev'])) {
                $output->writeln('Company Name: ' . $companyInfo['nazev']);
                $output->writeln('Company DB: ' . $companyInfo['dbNazev']);
                $output->writeln('Company State: ' . ($companyInfo['stavEnum'] ?? 'N/A'));
            } else {
                $output->writeln('<error>Unable to retrieve company information.</error>');
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $output->writeln('<error>Server or company not reachable: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>Server and company are reachable and configured correctly.</info>');
        return Command::SUCCESS;
    }
}
