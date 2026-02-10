<?php

namespace VitexSoftware\AbraflexiCli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use AbraFlexi\Company;

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
                $company['stavEnum'] ?? 'N/A'
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }
}
