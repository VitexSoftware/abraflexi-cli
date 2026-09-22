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
        parent::configure();

        $this->setName('status')
            ->setDescription('Show information about configured company and server state');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $json = self::isJsonFormat($input);
        $options = $this->getAbraFlexiOptions();

        $tokenAuth = self::hasTokenAuth($options);

        if (!$json) {
            $output->writeln('<info>Configured AbraFlexi Connection:</info>');
            $output->writeln('URL: '.($options['url'] ?? 'Not set'));
            $output->writeln('User: '.($tokenAuth ? '(session token)' : ($options['user'] ?? 'Not set')));
            $output->writeln('Company: '.($options['company'] ?? 'Not set'));
        }

        if (empty($options['url']) || empty($options['company']) || !self::hasCredentials($options)) {
            if ($json) {
                self::writeJsonError($output, 'Some AbraFlexi connection parameters are missing.', [
                    'url' => $options['url'] ?? null,
                    'user' => $tokenAuth ? null : ($options['user'] ?? null),
                    'authMethod' => $tokenAuth ? 'token' : 'password',
                    'company' => $options['company'] ?? null,
                ]);
            } else {
                $output->writeln('<error>Some AbraFlexi connection parameters are missing.</error>');
            }

            return Command::FAILURE;
        }

        if (!$json) {
            $output->writeln('');
            $output->writeln('<info>Checking server and company state...</info>');
        }

        try {
            $companyClient = new Company($options['company'], $options);
            $companyInfo = $companyClient->getData();

            if (isset($companyInfo['nazev'])) {
                if ($json) {
                    self::writeJson($output, [
                        'status' => 'ok',
                        'url' => $options['url'],
                        'authMethod' => $tokenAuth ? 'token' : 'password',
                        'user' => $tokenAuth ? null : ($options['user'] ?? null),
                        'company' => $options['company'],
                        'reachable' => true,
                        'companyName' => $companyInfo['nazev'],
                        'companyDb' => $companyInfo['dbNazev'],
                        'companyState' => $companyInfo['stavEnum'] ?? null,
                    ]);

                    return Command::SUCCESS;
                }

                $output->writeln('Company Name: '.$companyInfo['nazev']);
                $output->writeln('Company DB: '.$companyInfo['dbNazev']);
                $output->writeln('Company State: '.($companyInfo['stavEnum'] ?? 'N/A'));
            } else {
                if ($json) {
                    self::writeJsonError($output, 'Unable to retrieve company information.');
                } else {
                    $output->writeln('<error>Unable to retrieve company information.</error>');
                }

                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            if ($json) {
                self::writeJsonError($output, 'Server or company not reachable: '.$e->getMessage());
            } else {
                $output->writeln('<error>Server or company not reachable: '.$e->getMessage().'</error>');
            }

            return Command::FAILURE;
        }

        $output->writeln('<info>Server and company are reachable and configured correctly.</info>');

        return Command::SUCCESS;
    }
}
