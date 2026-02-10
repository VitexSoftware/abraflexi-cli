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
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListCompaniesCommand extends BaseCommand
{
    protected static $defaultName = 'list-companies';

    protected function configure(): void
    {
        $this->setName('list-companies')
            ->setDescription('List all available companies in AbraFlexi');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $companyClient = new Company(null, $this->getAbraFlexiOptions());
        $companies = $companyClient->getColumnsFromAbraFlexi(['dbName', 'nazev', 'stavEnum']);

        if (empty($companies)) {
            $output->writeln('<info>No companies found.</info>');

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['DB Name', 'Name', 'Status']);

        foreach ($companies as $company) {
            $table->addRow([
                $company['dbName'],
                $company['nazev'],
                $company['stavEnum'] ?? 'N/A',
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }
}
