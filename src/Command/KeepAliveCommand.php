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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class KeepAliveCommand extends BaseCommand
{
    protected static $defaultName = 'keep-alive';

    protected function configure(): void
    {
        parent::configure();

        $this->setName('keep-alive')
            ->setDescription('Ping the AbraFlexi server to refresh the TTL of the current ABRAFLEXI_AUTHSESSID session token');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $json = self::isJsonFormat($input);
        $options = $this->getAbraFlexiOptions();

        if (empty($options['url']) || empty($options['authSessionId'])) {
            $message = 'ABRAFLEXI_URL and ABRAFLEXI_AUTHSESSID must be set to keep a session alive.';

            if ($json) {
                self::writeJsonError($output, $message);
            } else {
                $output->writeln("<error>{$message}</error>");
            }

            return Command::FAILURE;
        }

        $client = new RO(null, $options);

        try {
            $ok = $client->keepAlive();

            if (!$ok) {
                $message = 'Session token is no longer valid; obtain a new one with the login command.';

                if ($json) {
                    self::writeJsonError($output, $message, ['responseCode' => $client->lastResponseCode]);
                } else {
                    $output->writeln("<error>{$message}</error>");
                }

                return Command::FAILURE;
            }

            if ($json) {
                self::writeJson($output, ['status' => 'ok']);
            } else {
                $output->writeln('<info>Session kept alive.</info>');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $message = 'Keep-alive error: '.$e->getMessage();

            if ($json) {
                self::writeJsonError($output, $message);
            } else {
                $output->writeln("<error>{$message}</error>");
            }

            return Command::FAILURE;
        }
    }
}
