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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Raw REST call, the Flexplorer query form.
 */
class QueryCommand extends BaseCommand
{
    protected static $defaultName = 'query';

    protected function configure(): void
    {
        parent::configure();

        $this->setName('query')
            ->setDescription('Send a raw AbraFlexi REST request')
            ->addArgument('path', InputArgument::REQUIRED, 'Path under the server, e.g. adresar.json or /c/demo/adresar/1.json')
            ->addOption('method', 'm', InputOption::VALUE_REQUIRED, 'HTTP method', 'GET')
            ->addOption('body', null, InputOption::VALUE_OPTIONAL, 'Raw request body');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $json = self::isJsonFormat($input);
        $options = $this->getAbraFlexiOptions();
        $path = (string) $input->getArgument('path');
        $method = strtoupper((string) $input->getOption('method'));
        $body = $input->getOption('body');
        $company = (string) ($options['company'] ?? '');

        if (!str_starts_with($path, 'http') && !str_starts_with($path, '/')) {
            $path = '/c/'.$company.'/'.ltrim($path, '/');
        }

        $client = new RO(null, $options);

        if (\is_string($body) && $body !== '') {
            $client->postFields = $body;
        }

        try {
            $result = $client->performRequest($path, $method);
            $payload = [
                'responseCode' => $client->lastResponseCode,
                'body' => $result === false ? null : $result,
            ];

            if ($client->lastResponseCode >= 400 || $result === false) {
                if ($json) {
                    self::writeJsonError($output, 'Request failed', [
                        'responseCode' => $client->lastResponseCode,
                        'errors' => $client->getErrors() ?: [],
                        'body' => $payload['body'],
                    ]);
                } else {
                    $output->writeln('<error>Request failed (HTTP '.$client->lastResponseCode.')</error>');
                }

                return Command::FAILURE;
            }

            if ($json) {
                self::writeJson($output, $payload);
            } else {
                $output->writeln((string) json_encode($payload['body'], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            if ($json) {
                self::writeJsonError($output, 'Error: '.$e->getMessage(), [
                    'responseCode' => $client->lastResponseCode,
                ]);
            } else {
                $output->writeln('<error>Error: '.$e->getMessage().'</error>');
            }

            return Command::FAILURE;
        }
    }
}
