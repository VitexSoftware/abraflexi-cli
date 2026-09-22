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

abstract class BaseCommand extends Command
{
    protected RO $connection;

    // Removed empty configure() to avoid potential issues with child commands
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $config = [
            'url' => getenv('ABRAFLEXI_URL'),
            'user' => getenv('ABRAFLEXI_USER'),
            'password' => getenv('ABRAFLEXI_PASSWORD'),
            'company' => getenv('ABRAFLEXI_COMPANY'),
        ];

        // Basic connection check/setup could go here if needed
    }

    protected function configure(): void
    {
        $this->addOption(
            'format',
            null,
            InputOption::VALUE_REQUIRED,
            'Output format: text (default) or json',
            'text'
        );
    }

    protected function getAbraFlexiOptions(): array
    {
        $options = [
            'verify' => false,
        ];

        if (getenv('ABRAFLEXI_URL')) {
            $options['url'] = getenv('ABRAFLEXI_URL');
        }

        if (getenv('ABRAFLEXI_LOGIN')) {
            $options['user'] = getenv('ABRAFLEXI_LOGIN');
        } elseif (getenv('ABRAFLEXI_USER')) {
            $options['user'] = getenv('ABRAFLEXI_USER');
        }

        if (getenv('ABRAFLEXI_PASSWORD')) {
            $options['password'] = getenv('ABRAFLEXI_PASSWORD');
        }

        if (getenv('ABRAFLEXI_COMPANY')) {
            $options['company'] = getenv('ABRAFLEXI_COMPANY');
        }

        if (getenv('ABRAFLEXI_AUTHSESSID')) {
            $options['authSessionId'] = getenv('ABRAFLEXI_AUTHSESSID');
        }

        return $options;
    }

    /**
     * Whether the configured connection uses a session token instead of login/password.
     */
    protected static function hasTokenAuth(array $options): bool
    {
        return !empty($options['authSessionId']);
    }

    /**
     * Whether the configured connection has enough credentials to authenticate,
     * either a session token (authSessionId) or a login/password pair.
     */
    protected static function hasCredentials(array $options): bool
    {
        return self::hasTokenAuth($options) || (!empty($options['user']) && !empty($options['password']));
    }

    /**
     * Whether the command was invoked with --format=json.
     */
    protected static function isJsonFormat(InputInterface $input): bool
    {
        return strtolower((string) $input->getOption('format')) === 'json';
    }

    /**
     * Write pretty-printed JSON to the output.
     */
    protected static function writeJson(OutputInterface $output, mixed $data): void
    {
        $output->writeln(json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));
    }

    /**
     * Write a standard JSON error envelope: {"status":"error","message":"...", ...$extra}.
     */
    protected static function writeJsonError(OutputInterface $output, string $message, array $extra = []): void
    {
        self::writeJson($output, array_merge(['status' => 'error', 'message' => $message], $extra));
    }
}
