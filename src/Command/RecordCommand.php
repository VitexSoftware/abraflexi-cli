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
use AbraFlexi\RW;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RecordCommand extends BaseCommand
{
    protected static $defaultName = 'record';

    protected function configure(): void
    {
        $this
            ->setName('record')
            ->setDescription('Interact with specific evidence records')
            ->addArgument('evidence', InputArgument::REQUIRED, 'Evidence name (e.g., faktura-vydana, banka)')
            ->addArgument('operation', InputArgument::OPTIONAL, 'Operation: list, show, create', 'list')
            ->addArgument('id', InputArgument::OPTIONAL, 'Record ID (for show)')
            ->addOption('columns', 'c', InputOption::VALUE_OPTIONAL, 'Comma separated list of columns', 'id,kod,nazev')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Limit results', 20)
            ->addOption('start', 's', InputOption::VALUE_OPTIONAL, 'Start page/offset for pagination')
            ->addOption('order', 'o', InputOption::VALUE_OPTIONAL, 'Ordering of results (e.g., nazev@A)')
            ->addOption('filter', 'f', InputOption::VALUE_OPTIONAL, 'Filtering query (e.g., nazev BEGINS \'A\')')
            ->addOption('detail', 'd', InputOption::VALUE_OPTIONAL, 'Level of detail (summary, full, id, ...)')
            ->addOption('relations', 'r', InputOption::VALUE_OPTIONAL, 'Include relations')
            ->addOption('includes', 'i', InputOption::VALUE_OPTIONAL, 'Include related objects')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Test run without making changes')
            ->addOption('add-row-count', null, InputOption::VALUE_NONE, 'Add total row count to output')
            ->addOption('data', null, InputOption::VALUE_OPTIONAL, 'JSON data for create operation')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force create even if mandatory fields are missing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $evidence = $input->getArgument('evidence');
        $operation = $input->getArgument('operation');
        $columns = explode(',', $input->getOption('columns'));

        $options = $this->getAbraFlexiOptions();
        $options['evidence'] = $evidence;

        if ($input->getOption('filter')) {
            $options['filter'] = $input->getOption('filter');
        }

        if ($input->getOption('detail')) {
            $options['detail'] = $input->getOption('detail');
        }

        $client = new RO(null, $options);

        // Pass extra params to defaultUrlParams
        $params = [];

        foreach (['order', 'relations', 'includes', 'dry-run', 'add-row-count'] as $param) {
            $val = $input->getOption($param);

            if ($val) {
                $client->defaultUrlParams[$param] = $val;
                $params[$param] = $val;
            }
        }

        if ($operation === 'list') {
            $limit = (int) $input->getOption('limit');
            $start = $input->getOption('start');
            $listParams = ['limit' => $limit];

            if ($start !== null) {
                $listParams['start'] = (int) $start;
            }

            return self::handleList($client, $columns, $listParams, $output);
        }

        if ($operation === 'show') {
            $id = $input->getArgument('id');

            if (!$id) {
                $output->writeln('<error>ID is required for show operation</error>');

                return Command::FAILURE;
            }

            return self::handleShow($client, $id, $output);
        }

        if ($operation === 'create') {
            return $this->handleCreate($evidence, $input, $output);
        }

        $output->writeln("<error>Unsupported operation: {$operation}</error>");

        return Command::FAILURE;
    }

    private static function handleList(RO $client, array $columns, array $params, OutputInterface $output): int
    {
        $records = $client->getColumnsFromAbraFlexi($columns, $params);

        if (empty($records)) {
            $output->writeln('<info>No records found.</info>');

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders($columns);

        foreach ($records as $record) {
            $row = [];

            foreach ($columns as $col) {
                $row[] = $record[$col] ?? '';
            }

            $table->addRow($row);
        }

        $table->render();

        return Command::SUCCESS;
    }

    private static function handleShow(RO $client, $id, OutputInterface $output): int
    {
        $record = $client->getColumnsFromAbraFlexi('*', ['id' => $id]);

        if (empty($record)) {
            $output->writeln("<error>Record {$id} not found.</error>");

            return Command::FAILURE;
        }

        // record is usually a list with one item
        if (isset($record[0])) {
            $record = $record[0];
        }

        foreach ($record as $key => $value) {
            if (\is_array($value)) {
                $value = json_encode($value);
            }

            $output->writeln("<info>{$key}</info>: {$value}");
        }

        return Command::SUCCESS;
    }

    /**
     * Handle record creation with mandatory field validation.
     *
     * @param string          $evidence Evidence name
     * @param InputInterface  $input    Input interface
     * @param OutputInterface $output   Output interface
     *
     * @return int Command exit code
     */
    private function handleCreate(string $evidence, InputInterface $input, OutputInterface $output): int
    {
        $jsonData = $input->getOption('data');
        $force = $input->getOption('force');
        $dryRun = $input->getOption('dry-run');

        $data = [];

        if (!empty($jsonData)) {
            $data = json_decode($jsonData, true);

            if (!\is_array($data)) {
                $output->writeln('<error>Invalid JSON data provided</error>');

                return Command::FAILURE;
            }
        } else {
            // Collect individual field options
            $allOptions = $input->getOptions();
            $knownOptions = ['columns', 'limit', 'start', 'order', 'filter', 'detail', 'relations', 'includes', 'dry-run', 'add-row-count', 'data', 'force'];

            foreach ($allOptions as $key => $value) {
                if (!\in_array($key, $knownOptions, true) && $value !== null && $value !== false) {
                    $data[$key] = $value;
                }
            }
        }

        if (empty($data)) {
            $output->writeln('<error>No data provided for create operation</error>');
            $output->writeln('<info>Usage: record {evidence} create --data \'{"field":"value"}\' or --field=value</info>');
            self::showMandatoryFields($evidence, $output);

            return Command::FAILURE;
        }

        // Check for missing mandatory fields
        $missingFields = PropertiesHelper::getMissingMandatoryFields($evidence, $data);

        if (!empty($missingFields)) {
            $output->writeln('<comment>Warning: The following mandatory fields are missing:</comment>');

            foreach ($missingFields as $fieldName => $fieldProps) {
                $output->writeln('  <comment>- '.PropertiesHelper::formatFieldInfo($fieldName, $fieldProps).'</comment>');
            }

            $output->writeln('');

            if (!$force) {
                $output->writeln('<error>Record creation aborted. Use --force to create anyway.</error>');

                return Command::FAILURE;
            }

            $output->writeln('<comment>Proceeding with --force flag...</comment>');
        }

        // Create the record using RW class
        $options = $this->getAbraFlexiOptions();
        $options['evidence'] = $evidence;

        if ($dryRun) {
            $options['dry-run'] = true;
        }

        $client = new RW(null, $options);
        $client->setData($data);

        try {
            $result = $client->insertToAbraFlexi();

            if ($client->lastResponseCode === 201 || ($dryRun && $client->lastResponseCode === 200)) {
                if ($dryRun) {
                    $output->writeln('<info>Dry-run successful. Record would be created with the following data:</info>');
                } else {
                    $output->writeln('<info>Record created successfully!</info>');
                }

                if ($client->lastInsertedID) {
                    $output->writeln("<info>ID:</info> {$client->lastInsertedID}");
                }

                $recordIdent = $client->getRecordIdent();

                if ($recordIdent) {
                    $output->writeln("<info>Record Ident:</info> {$recordIdent}");
                }

                if (!empty($result) && \is_array($result)) {
                    foreach ($result as $item) {
                        if (\is_array($item)) {
                            foreach ($item as $key => $value) {
                                if (\is_array($value)) {
                                    $value = json_encode($value);
                                }

                                $output->writeln("<info>{$key}</info>: {$value}");
                            }
                        }
                    }
                }

                return Command::SUCCESS;
            }

            $output->writeln('<error>Failed to create record</error>');
            $output->writeln("<error>Response code: {$client->lastResponseCode}</error>");

            if (!empty($client->errors)) {
                foreach ($client->errors as $error) {
                    if (\is_array($error)) {
                        $output->writeln('<error>'.($error['message'] ?? json_encode($error)).'</error>');
                    } else {
                        $output->writeln("<error>{$error}</error>");
                    }
                }
            }

            return Command::FAILURE;
        } catch (\Exception $e) {
            $output->writeln('<error>Error: '.$e->getMessage().'</error>');

            return Command::FAILURE;
        }
    }

    /**
     * Display mandatory fields for an evidence.
     *
     * @param string          $evidence Evidence name
     * @param OutputInterface $output   Output interface
     */
    private static function showMandatoryFields(string $evidence, OutputInterface $output): void
    {
        $mandatory = PropertiesHelper::getMandatoryFields($evidence);

        if (empty($mandatory)) {
            $output->writeln("<comment>No mandatory field information found for evidence '{$evidence}'</comment>");

            return;
        }

        $output->writeln('');
        $output->writeln("<info>Mandatory fields for '{$evidence}':</info>");

        foreach ($mandatory as $fieldName => $fieldProps) {
            $output->writeln('  - '.PropertiesHelper::formatFieldInfo($fieldName, $fieldProps));
        }
    }
}
