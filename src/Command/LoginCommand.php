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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class LoginCommand extends BaseCommand
{
    protected static $defaultName = 'login';

    protected function configure(): void
    {
        parent::configure();

        $this->setName('login')
            ->setDescription('Exchange ABRAFLEXI_LOGIN/ABRAFLEXI_PASSWORD for a short-lived authSessionId session token')
            ->addOption('otp', null, InputOption::VALUE_OPTIONAL, 'One-time password (2FA), if required by the server');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $json = self::isJsonFormat($input);
        $options = $this->getAbraFlexiOptions();

        if (empty($options['url']) || empty($options['user']) || empty($options['password'])) {
            $message = 'ABRAFLEXI_URL, ABRAFLEXI_LOGIN (or ABRAFLEXI_USER) and ABRAFLEXI_PASSWORD must be set to obtain a session token.';

            if ($json) {
                self::writeJsonError($output, $message);
            } else {
                $output->writeln("<error>{$message}</error>");
            }

            return Command::FAILURE;
        }

        $client = new RO(null, $options);
        $otp = $input->getOption('otp');

        try {
            $authSessionId = $client->requestAuthSessionID($options['user'], $options['password'], $otp);

            if (empty($authSessionId)) {
                $message = 'Login failed: server did not return an authSessionId.';

                if ($json) {
                    self::writeJsonError($output, $message, ['responseCode' => $client->lastResponseCode]);
                } else {
                    $output->writeln("<error>{$message}</error>");
                }

                return Command::FAILURE;
            }

            if ($json) {
                self::writeJson($output, [
                    'status' => 'ok',
                    'authSessionId' => $authSessionId,
                    'refreshToken' => $client->refreshToken,
                ]);

                return Command::SUCCESS;
            }

            $output->writeln('<info>Login successful.</info>');
            $output->writeln("authSessionId: {$authSessionId}");

            if ($client->refreshToken) {
                $output->writeln("refreshToken: {$client->refreshToken}");
            }

            $output->writeln('');
            $output->writeln('<comment>Set ABRAFLEXI_AUTHSESSID to this value to authenticate by token instead of password.</comment>');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $message = 'Login error: '.$e->getMessage();

            if ($json) {
                self::writeJsonError($output, $message);
            } else {
                $output->writeln("<error>{$message}</error>");
            }

            return Command::FAILURE;
        }
    }
}
