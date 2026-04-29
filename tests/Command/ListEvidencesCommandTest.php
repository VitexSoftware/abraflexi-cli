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
use VitexSoftware\AbraflexiCli\Command\ListEvidencesCommand;

class ListEvidencesCommandTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $this->app = new Application();
        $this->app->add(new ListEvidencesCommand());
    }

    public function testCommandName(): void
    {
        $command = $this->app->find('list-evidences');
        $this->assertSame('list-evidences', $command->getName());
    }

    public function testCommandDescription(): void
    {
        $command = $this->app->find('list-evidences');
        $this->assertNotEmpty($command->getDescription());
    }

    public function testCommandHasNoArguments(): void
    {
        $command = $this->app->find('list-evidences');
        $definition = $command->getDefinition();
        $this->assertCount(0, $definition->getArguments());
    }

    public function testCommandIsRegisteredInApplication(): void
    {
        $this->assertTrue($this->app->has('list-evidences'));
    }
}
