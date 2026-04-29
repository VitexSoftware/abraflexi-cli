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

namespace VitexSoftware\AbraflexiCli\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use VitexSoftware\AbraflexiCli\Command\StatusCommand;

class StatusCommandTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $this->app = new Application();
        $this->app->add(new StatusCommand());
    }

    public function testCommandName(): void
    {
        $command = $this->app->find('status');
        $this->assertSame('status', $command->getName());
    }

    public function testCommandDescription(): void
    {
        $command = $this->app->find('status');
        $this->assertNotEmpty($command->getDescription());
    }

    public function testCommandHasNoArguments(): void
    {
        $command = $this->app->find('status');
        $definition = $command->getDefinition();
        $this->assertCount(0, $definition->getArguments());
    }

    public function testCommandIsRegisteredInApplication(): void
    {
        $this->assertTrue($this->app->has('status'));
    }

    public function testCommandFailsWithoutConfiguration(): void
    {
        // Unset all connection env vars to simulate a missing configuration.
        foreach (['ABRAFLEXI_URL', 'ABRAFLEXI_USER', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY'] as $var) {
            putenv($var);
        }

        $command = $this->app->find('status');
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('missing', $tester->getDisplay());
    }
}
