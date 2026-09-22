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

use AbraFlexi\EvidenceList;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListEvidencesCommand extends BaseCommand
{
    protected static $defaultName = 'list-evidences';

    protected function configure(): void
    {
        parent::configure();

        $this->setName('list-evidences')
            ->setDescription('List all available evidences in AbraFlexi');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $evidenceClient = new EvidenceList(null, $this->getAbraFlexiOptions());
        $evidences = $evidenceClient->getColumnsFromAbraFlexi(['evidenceName', 'evidencePath', 'dbName'], ['limit' => 0]);

        if (empty($evidences) && !self::isJsonFormat($input)) {
            $output->writeln('<info>No evidences found.</info>');

            return Command::SUCCESS;
        }

        $rows = [];

        foreach ($evidences ?: [] as $name => $evidence) {
            $path = $evidence['dbName'] ?? $evidence['evidencePath'] ?? $name;
            $nameStr = $evidence['evidenceName'] ?? (\AbraFlexi\EvidenceList::$name[$path] ?? (\AbraFlexi\EvidenceList::$evidences[$path]['evidenceName'] ?? ''));
            $rows[] = [
                'path' => $path,
                'name' => $nameStr,
                'description' => $evidence['popis'] ?? '',
            ];
        }

        if (self::isJsonFormat($input)) {
            self::writeJson($output, $rows);

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Path', 'Name', 'Description']);

        foreach ($rows as $row) {
            $table->addRow([$row['path'], $row['name'], $row['description']]);
        }

        $table->render();

        return Command::SUCCESS;
    }
}
