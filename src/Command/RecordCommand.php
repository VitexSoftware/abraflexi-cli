<?php

namespace VitexSoftware\AbraflexiCli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use AbraFlexi\RO;

class RecordCommand extends BaseCommand
{
    protected static $defaultName = 'record';

    protected function configure(): void
    {
        $this
            ->setName('record')
            ->setDescription('Interact with specific evidence records')
            ->addArgument('evidence', InputArgument::REQUIRED, 'Evidence name (e.g., faktura-vydana)')
            ->addArgument('operation', InputArgument::OPTIONAL, 'Operation: list, show', 'list')
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
            ->addOption('add-row-count', null, InputOption::VALUE_NONE, 'Add total row count to output');
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
            return $this->handleList($client, $columns, $listParams, $output);
        }

        if ($operation === 'show') {
            $id = $input->getArgument('id');
            if (!$id) {
                $output->writeln('<error>ID is required for show operation</error>');
                return Command::FAILURE;
            }
            return $this->handleShow($client, $id, $output);
        }

        $output->writeln("<error>Unsupported operation: $operation</error>");
        return Command::FAILURE;
    }

    private function handleList(RO $client, array $columns, array $params, OutputInterface $output): int
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

    private function handleShow(RO $client, $id, OutputInterface $output): int
    {
        $record = $client->getColumnsFromAbraFlexi('*', ['id' => $id]);

        if (empty($record)) {
            $output->writeln("<error>Record $id not found.</error>");
            return Command::FAILURE;
        }

        // record is usually a list with one item
        if (isset($record[0])) {
            $record = $record[0];
        }

        foreach ($record as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $output->writeln("<info>$key</info>: $value");
        }

        return Command::SUCCESS;
    }
}
