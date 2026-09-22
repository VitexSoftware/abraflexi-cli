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

use AbraFlexi\Changes;
use AbraFlexi\Hooks;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Changes API status and webhook registration, as in Flexplorer's Changes screen.
 */
class ChangesCommand extends BaseCommand
{
    protected static $defaultName = 'changes';

    protected function configure(): void
    {
        parent::configure();

        $this->setName('changes')
            ->setDescription('Changes API status and webhook registration')
            ->addArgument('action', InputArgument::OPTIONAL, 'status, enable, disable, register, unregister', 'status')
            ->addArgument('id', InputArgument::OPTIONAL, 'Hook id for unregister')
            ->addOption('url', null, InputOption::VALUE_OPTIONAL, 'Webhook URL for register')
            ->addOption('hook-format', null, InputOption::VALUE_REQUIRED, 'Webhook payload format: json or xml', 'json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $json = self::isJsonFormat($input);
        $action = (string) $input->getArgument('action');
        $options = $this->getAbraFlexiOptions();

        try {
            if ($action === 'enable' || $action === 'disable') {
                $changes = new Changes(null, $options);
                $ok = $action === 'enable' ? $changes->enable() : $changes->disable();

                if (!$ok) {
                    if ($json) {
                        self::writeJsonError($output, 'Changes API '.$action.' failed', [
                            'responseCode' => $changes->lastResponseCode,
                        ]);
                    } else {
                        $output->writeln('<error>Changes API '.$action.' failed</error>');
                    }

                    return Command::FAILURE;
                }

                if ($json) {
                    self::writeJson($output, ['status' => 'ok', 'enabled' => $action === 'enable']);
                } else {
                    $output->writeln('<info>Changes API '.$action.'d</info>');
                }

                return Command::SUCCESS;
            }

            if ($action === 'register') {
                $url = trim((string) $input->getOption('url'));

                if ($url === '') {
                    if ($json) {
                        self::writeJsonError($output, 'Webhook URL is required (--url)');
                    } else {
                        $output->writeln('<error>Webhook URL is required (--url)</error>');
                    }

                    return Command::FAILURE;
                }

                $hooks = new Hooks(null, $options);
                $format = strtolower((string) $input->getOption('hook-format')) === 'xml' ? 'xml' : 'json';
                $ok = $hooks->register($url, $format);

                if (!$ok) {
                    if ($json) {
                        self::writeJsonError($output, 'Webhook was not registered', [
                            'responseCode' => $hooks->lastResponseCode,
                            'errors' => $hooks->getErrors() ?: [],
                        ]);
                    } else {
                        $output->writeln('<error>Webhook was not registered</error>');
                    }

                    return Command::FAILURE;
                }

                if ($json) {
                    self::writeJson($output, ['status' => 'ok', 'url' => $url, 'format' => $format]);
                } else {
                    $output->writeln('<info>Webhook registered.</info>');
                }

                return Command::SUCCESS;
            }

            if ($action === 'unregister') {
                $id = (string) $input->getArgument('id');

                if ($id === '') {
                    if ($json) {
                        self::writeJsonError($output, 'Hook id is required');
                    } else {
                        $output->writeln('<error>Hook id is required</error>');
                    }

                    return Command::FAILURE;
                }

                $hooks = new Hooks(null, $options);
                $ok = $hooks->unregister($id);

                if (!$ok) {
                    if ($json) {
                        self::writeJsonError($output, 'Webhook was not removed', [
                            'responseCode' => $hooks->lastResponseCode,
                        ]);
                    } else {
                        $output->writeln('<error>Webhook was not removed</error>');
                    }

                    return Command::FAILURE;
                }

                if ($json) {
                    self::writeJson($output, ['status' => 'ok', 'id' => $id]);
                } else {
                    $output->writeln('<info>Webhook removed.</info>');
                }

                return Command::SUCCESS;
            }

            if ($action !== 'status') {
                if ($json) {
                    self::writeJsonError($output, "Unsupported changes action: {$action}");
                } else {
                    $output->writeln("<error>Unsupported changes action: {$action}</error>");
                }

                return Command::FAILURE;
            }

            $changes = new Changes(null, $options);
            $enabled = $changes->getStatus();
            $version = null;

            try {
                $version = $changes->getGlobalVersion();
            } catch (\Throwable $e) {
                $version = null;
            }

            $hooks = [];

            try {
                $listed = (new Hooks(null, $options))->getAllFromAbraFlexi();

                if (\is_array($listed)) {
                    $hooks = array_values($listed);
                }
            } catch (\Throwable $e) {
                $hooks = [];
            }

            $payload = [
                'enabled' => (bool) $enabled,
                'globalVersion' => $version,
                'hooks' => $hooks,
            ];

            if ($json) {
                self::writeJson($output, $payload);
            } else {
                $output->writeln('enabled: '.($enabled ? 'yes' : 'no'));
                $output->writeln('globalVersion: '.(string) $version);
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
}
