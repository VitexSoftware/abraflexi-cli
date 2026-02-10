<?php

namespace Vitexsoftware\AbraflexiCli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use AbraFlexi\EvidenceList;

class ListEvidencesCommand extends BaseCommand
{
    protected static $defaultName = 'list-evidences';

    protected function configure(): void
    {
        $this->setName('list-evidences')
            ->setDescription('List all available evidences in AbraFlexi');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $evidenceClient = new EvidenceList(null, $this->getAbraFlexiOptions());
        $evidences = $evidenceClient->getColumnsFromAbraFlexi(['nazev', 'popis', 'dbName'], ['limit' => 0]);

        if (empty($evidences)) {
            $output->writeln('<info>No evidences found.</info>');
            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Path', 'Name', 'Description']);

        foreach ($evidences as $name => $evidence) {
            $table->addRow([
                $evidence['dbName'] ?? $name,
                $evidence['nazev'] ?? '',
                $evidence['popis'] ?? ''
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }
}
