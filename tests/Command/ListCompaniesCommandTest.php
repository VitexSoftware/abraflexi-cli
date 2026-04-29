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
use VitexSoftware\AbraflexiCli\Command\ListCompaniesCommand;

class ListCompaniesCommandTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $this->app = new Application();
        $this->app->add(new ListCompaniesCommand());
    }

    public function testCommandName(): void
    {
        $command = $this->app->find('list-companies');
        $this->assertSame('list-companies', $command->getName());
    }

    public function testCommandDescription(): void
    {
        $command = $this->app->find('list-companies');
        $this->assertNotEmpty($command->getDescription());
    }

    public function testCommandHasNoArguments(): void
    {
        $command = $this->app->find('list-companies');
        $definition = $command->getDefinition();
        $this->assertCount(0, $definition->getArguments());
    }

    public function testCommandIsRegisteredInApplication(): void
    {
        $this->assertTrue($this->app->has('list-companies'));
    }
}
