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
        parent::configure();

        $this->setName('list-companies')
            ->setDescription('List all available companies in AbraFlexi');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $options = $this->getAbraFlexiOptions();
        // /c.json lists every company on the server. A configured company would
        // narrow the request to /c/{company} and return only that one.
        $options['company'] = null;
        $companyClient = new Company(null, $options);
        $companies = $companyClient->getFlexiData();

        if (!\is_array($companies)) {
            if (self::isJsonFormat($input)) {
                self::writeJsonError($output, 'Unable to list companies.');
            } else {
                $output->writeln('<error>Unable to list companies.</error>');
            }

            return Command::FAILURE;
        }

        $rows = self::companyRows($companies);

        if (self::isJsonFormat($input)) {
            self::writeJson($output, $rows);

            return Command::SUCCESS;
        }

        $companies = $rows;

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
                $company['stavEnum'],
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }

    /**
     * @param array<int|string, mixed> $companies
     *
     * @return list<array{dbName: ?string, nazev: ?string, stavEnum: string}>
     */
    private static function companyRows(array $companies): array
    {
        if (isset($companies['dbNazev']) || isset($companies['dbName'])) {
            $companies = [$companies];
        }

        $rows = [];

        foreach ($companies as $company) {
            if (!\is_array($company)) {
                continue;
            }

            $rows[] = [
                'dbName' => $company['dbNazev'] ?? $company['dbName'] ?? null,
                'nazev' => $company['nazev'] ?? null,
                'stavEnum' => $company['stavEnum'] ?? 'N/A',
            ];
        }

        return $rows;
    }
}
