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
use AbraFlexi\Stitek;
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
        parent::configure();

        $this
            ->setName('record')
            ->setDescription('Interact with specific evidence records')
            ->addArgument('evidence', InputArgument::REQUIRED, 'Evidence name (e.g., faktura-vydana, banka)')
            ->addArgument('operation', InputArgument::OPTIONAL, 'Operation: list, show, create, update, delete, properties, search, attachments, download', 'list')
            ->addArgument('id', InputArgument::OPTIONAL, 'Record ID (for show, update, delete, attachments); attachment ID for download (evidence "priloha")')
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
            ->addOption('data', null, InputOption::VALUE_OPTIONAL, 'JSON data for create/update')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force create even if mandatory fields are missing')
            ->addOption('query', null, InputOption::VALUE_OPTIONAL, 'Search text for the search operation')
            ->addOption('output', 'O', InputOption::VALUE_OPTIONAL, 'Destination file or directory for the download operation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $evidence = $input->getArgument('evidence');
        $operation = $input->getArgument('operation');
        $columns = explode(',', $input->getOption('columns'));
        $json = self::isJsonFormat($input);

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

            return self::handleList($client, $columns, $listParams, $output, $json);
        }

        if ($operation === 'show') {
            $id = $input->getArgument('id');

            if (!$id) {
                if ($json) {
                    self::writeJsonError($output, 'ID is required for show operation');
                } else {
                    $output->writeln('<error>ID is required for show operation</error>');
                }

                return Command::FAILURE;
            }

            return self::handleShow($client, $id, $output, $json);
        }

        if ($operation === 'create') {
            return $this->handleCreate($evidence, $input, $output);
        }

        if ($operation === 'update') {
            return $this->handleUpdate($evidence, $input, $output);
        }

        if ($operation === 'delete') {
            return $this->handleDelete($evidence, $input, $output);
        }

        if ($operation === 'properties') {
            return $this->handleProperties($evidence, $output, $json);
        }

        if ($operation === 'search') {
            return $this->handleSearch($evidence, $input, $output, $json);
        }

        if ($operation === 'attachments') {
            $id = $input->getArgument('id');

            if (!$id) {
                if ($json) {
                    self::writeJsonError($output, 'ID is required for attachments operation');
                } else {
                    $output->writeln('<error>ID is required for attachments operation</error>');
                }

                return Command::FAILURE;
            }

            return $this->handleAttachments($evidence, (string) $id, $output, $json);
        }

        if ($operation === 'download') {
            $id = $input->getArgument('id');

            if (!$id) {
                if ($json) {
                    self::writeJsonError($output, 'ID (attachment ID) is required for download operation');
                } else {
                    $output->writeln('<error>ID (attachment ID) is required for download operation</error>');
                }

                return Command::FAILURE;
            }

            return $this->handleDownload((string) $id, $input, $output, $json);
        }

        if ($json) {
            self::writeJsonError($output, "Unsupported operation: {$operation}");
        } else {
            $output->writeln("<error>Unsupported operation: {$operation}</error>");
        }

        return Command::FAILURE;
    }

    private static function handleList(RO $client, array $columns, array $params, OutputInterface $output, bool $json): int
    {
        $records = $client->getColumnsFromAbraFlexi($columns, $params);

        if ($json) {
            $rows = [];

            foreach ($records ?: [] as $record) {
                $row = [];

                foreach ($columns as $col) {
                    $row[$col] = $record[$col] ?? null;
                }

                $rows[] = $row;
            }

            self::writeJson($output, $rows);

            return Command::SUCCESS;
        }

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

    private static function handleShow(RO $client, $id, OutputInterface $output, bool $json): int
    {
        $record = $client->getColumnsFromAbraFlexi('*', ['id' => $id]);

        if (empty($record)) {
            if ($json) {
                self::writeJsonError($output, "Record {$id} not found.");
            } else {
                $output->writeln("<error>Record {$id} not found.</error>");
            }

            return Command::FAILURE;
        }

        // record is usually a list with one item
        if (isset($record[0])) {
            $record = $record[0];
        }

        if ($json) {
            self::writeJson($output, $record);

            return Command::SUCCESS;
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
        $json = self::isJsonFormat($input);
        $jsonData = $input->getOption('data');
        $force = $input->getOption('force');
        $dryRun = $input->getOption('dry-run');

        $data = [];

        if (!empty($jsonData)) {
            $data = json_decode($jsonData, true);

            if (!\is_array($data)) {
                if ($json) {
                    self::writeJsonError($output, 'Invalid JSON data provided');
                } else {
                    $output->writeln('<error>Invalid JSON data provided</error>');
                }

                return Command::FAILURE;
            }
        } else {
            // Collect individual field options
            $allOptions = $input->getOptions();
            $knownOptions = ['format', 'columns', 'limit', 'start', 'order', 'filter', 'detail', 'relations', 'includes', 'dry-run', 'add-row-count', 'data', 'force', 'query'];

            foreach ($allOptions as $key => $value) {
                if (!\in_array($key, $knownOptions, true) && $value !== null && $value !== false) {
                    $data[$key] = $value;
                }
            }
        }

        if (empty($data)) {
            if ($json) {
                $mandatory = PropertiesHelper::getMandatoryFields($evidence);
                self::writeJsonError($output, 'No data provided for create operation', [
                    'usage' => "record {$evidence} create --data '{\"field\":\"value\"}' or --field=value",
                    'mandatoryFields' => $mandatory,
                    'mandatoryFieldsFormatted' => array_map(
                        static fn ($name, $props) => PropertiesHelper::formatFieldInfo($name, $props),
                        array_keys($mandatory),
                        $mandatory
                    ),
                ]);
            } else {
                $output->writeln('<error>No data provided for create operation</error>');
                $output->writeln('<info>Usage: record {evidence} create --data \'{"field":"value"}\' or --field=value</info>');
                self::showMandatoryFields($evidence, $output);
            }

            return Command::FAILURE;
        }

        // Check for missing mandatory fields
        $missingFields = PropertiesHelper::getMissingMandatoryFields($evidence, $data);

        if (!empty($missingFields)) {
            if ($json && !$force) {
                self::writeJsonError($output, 'Record creation aborted. Use --force to create anyway.', [
                    'missingFields' => $missingFields,
                    'missingFieldsFormatted' => array_map(
                        static fn ($name, $props) => PropertiesHelper::formatFieldInfo($name, $props),
                        array_keys($missingFields),
                        $missingFields
                    ),
                ]);

                return Command::FAILURE;
            }

            if (!$json) {
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
                if ($json) {
                    $fields = [];

                    if (!empty($result) && \is_array($result)) {
                        foreach ($result as $item) {
                            if (\is_array($item)) {
                                $fields += $item;
                            }
                        }
                    }

                    $payload = [
                        'status' => 'success',
                        'dryRun' => (bool) $dryRun,
                    ];

                    if ($client->lastInsertedID) {
                        $payload['id'] = $client->lastInsertedID;
                    }

                    $recordIdent = $client->getRecordIdent();

                    if ($recordIdent) {
                        $payload['ident'] = $recordIdent;
                    }

                    $payload['data'] = $fields;

                    self::writeJson($output, $payload);

                    return Command::SUCCESS;
                }

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

            if ($json) {
                self::writeJsonError($output, 'Failed to create record', [
                    'responseCode' => $client->lastResponseCode,
                    'errors' => $client->errors ?: [],
                ]);

                return Command::FAILURE;
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
            if ($json) {
                self::writeJsonError($output, 'Error: '.$e->getMessage());
            } else {
                $output->writeln('<error>Error: '.$e->getMessage().'</error>');
            }

            return Command::FAILURE;
        }
    }

    /**
     * PUT an existing record. AbraFlexi treats insertToAbraFlexi() as a save;
     * the id in the payload selects the record to update.
     */
    private function handleUpdate(string $evidence, InputInterface $input, OutputInterface $output): int
    {
        $json = self::isJsonFormat($input);
        $id = (string) $input->getArgument('id');
        $jsonData = $input->getOption('data');
        $dryRun = $input->getOption('dry-run');

        if ($id === '') {
            if ($json) {
                self::writeJsonError($output, 'ID is required for update operation');
            } else {
                $output->writeln('<error>ID is required for update operation</error>');
            }

            return Command::FAILURE;
        }

        $data = json_decode((string) $jsonData, true);

        if (!\is_array($data)) {
            if ($json) {
                self::writeJsonError($output, 'Invalid or missing JSON data for update');
            } else {
                $output->writeln('<error>Invalid or missing JSON data for update</error>');
            }

            return Command::FAILURE;
        }

        if (!isset($data['id'])) {
            $data['id'] = $id;
        }

        $options = $this->getAbraFlexiOptions();
        $options['evidence'] = $evidence;

        if ($dryRun) {
            $options['dry-run'] = true;
        }

        $client = new RW(null, $options);
        $client->setData($data);

        try {
            $client->insertToAbraFlexi();

            if ($client->lastResponseCode === 201 || $client->lastResponseCode === 200) {
                if ($json) {
                    self::writeJson($output, [
                        'status' => 'success',
                        'dryRun' => (bool) $dryRun,
                        'id' => $data['id'],
                    ]);
                } else {
                    $output->writeln('<info>Record updated.</info>');
                }

                return Command::SUCCESS;
            }

            if ($json) {
                self::writeJsonError($output, 'Failed to update record', [
                    'responseCode' => $client->lastResponseCode,
                    'errors' => $client->getErrors() ?: [],
                ]);
            } else {
                $output->writeln('<error>Failed to update record</error>');
            }

            return Command::FAILURE;
        } catch (\Exception $e) {
            if ($json) {
                self::writeJsonError($output, 'Error: '.$e->getMessage());
            } else {
                $output->writeln('<error>Error: '.$e->getMessage().'</error>');
            }

            return Command::FAILURE;
        }
    }

    private function handleDelete(string $evidence, InputInterface $input, OutputInterface $output): int
    {
        $json = self::isJsonFormat($input);
        $id = (string) $input->getArgument('id');

        if ($id === '') {
            if ($json) {
                self::writeJsonError($output, 'ID is required for delete operation');
            } else {
                $output->writeln('<error>ID is required for delete operation</error>');
            }

            return Command::FAILURE;
        }

        $options = $this->getAbraFlexiOptions();
        $options['evidence'] = $evidence;
        $client = new RW(null, $options);

        try {
            $deleted = $client->deleteFromAbraFlexi($id);

            if ($deleted) {
                if ($json) {
                    self::writeJson($output, ['status' => 'success', 'id' => $id]);
                } else {
                    $output->writeln('<info>Record deleted.</info>');
                }

                return Command::SUCCESS;
            }

            if ($json) {
                self::writeJsonError($output, 'Record was not deleted', [
                    'responseCode' => $client->lastResponseCode,
                    'errors' => $client->getErrors() ?: [],
                ]);
            } else {
                $output->writeln('<error>Record was not deleted</error>');
            }

            return Command::FAILURE;
        } catch (\Exception $e) {
            if ($json) {
                self::writeJsonError($output, 'Error: '.$e->getMessage());
            } else {
                $output->writeln('<error>Error: '.$e->getMessage().'</error>');
            }

            return Command::FAILURE;
        }
    }

    /**
     * Evidence structure: columns, relations and labels. Same information as
     * Flexplorer's evidence Info tab.
     */
    private function handleProperties(string $evidence, OutputInterface $output, bool $json): int
    {
        $options = $this->getAbraFlexiOptions();
        $options['evidence'] = $evidence;
        $client = new RO(null, $options);

        try {
            $rawColumns = $client->getOnlineColumnsInfo($evidence);

            if (!\is_array($rawColumns) || $rawColumns === []) {
                $rawColumns = $client->getColumnsInfo($evidence);
            }

            $columns = [];

            foreach ($rawColumns ?: [] as $name => $info) {
                if (!\is_array($info)) {
                    continue;
                }

                $columns[] = [
                    'name' => (string) $name,
                    'type' => (string) ($info['type'] ?? ''),
                    'mandatory' => ($info['mandatory'] ?? '') === 'true',
                    'writable' => ($info['isWritable'] ?? '') === 'true',
                    'title' => (string) ($info['title'] ?? ($info['name'] ?? '')),
                    'visible' => ($info['isVisible'] ?? 'true') === 'true',
                    'inSummary' => ($info['inSummary'] ?? '') === 'true',
                    'inDetail' => ($info['inDetail'] ?? '') === 'true',
                    'sortable' => ($info['isSortable'] ?? '') === 'true',
                    'maxLength' => isset($info['maxLength']) ? (int) $info['maxLength'] : null,
                    'relationEvidence' => (string) ($info['fkEvidencePath'] ?? ''),
                    'relationType' => (string) ($info['fkEvidenceType'] ?? ''),
                ];
            }

            $relations = [];

            foreach ($client->getRelationsInfo($evidence) ?: [] as $relation) {
                if (\is_array($relation)) {
                    $relations[] = [
                        'name' => (string) ($relation['name'] ?? ''),
                        'evidenceType' => (string) ($relation['evidenceType'] ?? ''),
                        'url' => (string) ($relation['url'] ?? ''),
                    ];
                } elseif (\is_string($relation)) {
                    $relations[] = ['name' => $relation, 'evidenceType' => '', 'url' => ''];
                }
            }

            $labels = [];

            try {
                foreach (Stitek::getAvailableLabels($client) ?: [] as $kod => $nazev) {
                    $labels[] = ['kod' => (string) $kod, 'nazev' => (string) $nazev];
                }
            } catch (\Throwable $e) {
                $labels = [];
            }

            $payload = [
                'evidence' => $evidence,
                'info' => $client->getEvidenceInfo($evidence),
                'columns' => $columns,
                'relations' => $relations,
                'labels' => $labels,
            ];

            if ($json) {
                self::writeJson($output, $payload);
            } else {
                $output->writeln('<info>'.$evidence.'</info>');

                foreach ($columns as $column) {
                    $output->writeln($column['name'].' '.$column['type']);
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            if ($json) {
                self::writeJsonError($output, 'Error: '.$e->getMessage());
            } else {
                $output->writeln('<error>Error: '.$e->getMessage().'</error>');
            }

            return Command::FAILURE;
        }
    }

    /**
     * List a record's attachments (přílohy): PDFs on documents, photos on
     * price-list items, etc. Metadata only (id, filename, content type,
     * size) - use the "download" operation to fetch a specific attachment's
     * bytes.
     */
    private function handleAttachments(string $evidence, string $id, OutputInterface $output, bool $json): int
    {
        $options = $this->getAbraFlexiOptions();
        $options['evidence'] = $evidence;

        try {
            $object = new RO($id, $options);
            $attachments = array_values(\AbraFlexi\Priloha::getAttachmentsList($object));

            if ($json) {
                self::writeJson($output, ['evidence' => $evidence, 'id' => $id, 'attachments' => $attachments]);
            } else {
                foreach ($attachments as $attachment) {
                    $output->writeln(
                        ($attachment['id'] ?? '?').' '.
                        ($attachment['nazSoub'] ?? $attachment['name'] ?? '').' '.
                        ($attachment['contentType'] ?? ''),
                    );
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            if ($json) {
                self::writeJsonError($output, 'Error: '.$e->getMessage());
            } else {
                $output->writeln('<error>Error: '.$e->getMessage().'</error>');
            }

            return Command::FAILURE;
        }
    }

    /**
     * Download one attachment's raw bytes to a local file (evidence must be
     * "priloha", $id its attachment ID - as listed by "attachments"). Writes
     * the file directly rather than embedding it in the JSON response, since
     * every other command channel here is JSON-only and attachments (scanned
     * PDFs, photos) can be several MB.
     */
    private function handleDownload(string $id, InputInterface $input, OutputInterface $output, bool $json): int
    {
        $destination = $input->getOption('output');

        if (!$destination) {
            if ($json) {
                self::writeJsonError($output, '--output=<path> is required for download operation');
            } else {
                $output->writeln('<error>--output=<path> is required for download operation</error>');
            }

            return Command::FAILURE;
        }

        $options = $this->getAbraFlexiOptions();
        $options['evidence'] = 'priloha';

        try {
            $meta = new RO($id, $options);

            if (!$meta->getDataValue('id')) {
                if ($json) {
                    self::writeJsonError($output, "Attachment {$id} not found.");
                } else {
                    $output->writeln("<error>Attachment {$id} not found.</error>");
                }

                return Command::FAILURE;
            }

            $written = \AbraFlexi\Priloha::saveToFile((int) $id, $destination);

            if ($written <= 0) {
                if ($json) {
                    self::writeJsonError($output, "Could not download attachment {$id}.");
                } else {
                    $output->writeln("<error>Could not download attachment {$id}.</error>");
                }

                return Command::FAILURE;
            }

            $savedPath = is_dir($destination) ? rtrim($destination, '/').'/'.$meta->getDataValue('nazSoub') : $destination;

            $payload = [
                'ok' => true,
                'id' => $id,
                'path' => $savedPath,
                'bytes' => $written,
                'contentType' => $meta->getDataValue('contentType'),
                'filename' => $meta->getDataValue('nazSoub'),
            ];

            if ($json) {
                self::writeJson($output, $payload);
            } else {
                $output->writeln("<info>Saved {$written} bytes to {$savedPath}</info>");
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            if ($json) {
                self::writeJsonError($output, 'Error: '.$e->getMessage());
            } else {
                $output->writeln('<error>Error: '.$e->getMessage().'</error>');
            }

            return Command::FAILURE;
        }
    }

    /**
     * Flexplorer searchString: OR of `column like 'term'` over string fields.
     */
    private function handleSearch(string $evidence, InputInterface $input, OutputInterface $output, bool $json): int
    {
        $term = trim((string) $input->getOption('query'));

        if ($term === '') {
            if ($json) {
                self::writeJsonError($output, 'Search text is required (--query)');
            } else {
                $output->writeln('<error>Search text is required (--query)</error>');
            }

            return Command::FAILURE;
        }

        $safe = str_replace("'", "''", $term);
        $options = $this->getAbraFlexiOptions();
        $options['evidence'] = $evidence;
        $client = new RO(null, $options);
        $props = $client->getColumnsInfo($evidence);
        $stringCols = [];

        if (\is_array($props)) {
            foreach ($props as $name => $info) {
                if (!\is_array($info) || ($info['type'] ?? '') !== 'string') {
                    continue;
                }

                if (\in_array($name, ['stitky', 'faNazev2'], true)) {
                    continue;
                }

                $stringCols[] = (string) $name;

                if (\count($stringCols) >= 12) {
                    break;
                }
            }
        }

        if ($stringCols === []) {
            $stringCols = ['kod', 'nazev'];
        }

        $parts = [];

        foreach ($stringCols as $column) {
            $parts[] = $column." like '".$safe."'";
        }

        $requested = ['id'];

        foreach (['kod', 'nazev'] as $column) {
            if (!\is_array($props) || isset($props[$column])) {
                $requested[] = $column;
            }
        }

        try {
            $records = $client->getColumnsFromAbraFlexi($requested, [
                'filter' => implode(' or ', $parts),
                'limit' => (int) $input->getOption('limit'),
            ]);

            if (!\is_array($records)) {
                $records = [];
            }

            if ($json) {
                self::writeJson($output, array_values($records));
            } else {
                $output->writeln('<info>'.\count($records).' match(es)</info>');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            if ($json) {
                self::writeJsonError($output, 'Error: '.$e->getMessage());
            } else {
                $output->writeln('<error>Error: '.$e->getMessage().'</error>');
            }

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
