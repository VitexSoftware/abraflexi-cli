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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use VitexSoftware\AbraflexiCli\Command\RecordCommand;

class RecordCommandTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $this->app = new Application();
        $this->app->add(new RecordCommand());
    }

    public function testCommandName(): void
    {
        $command = $this->app->find('record');
        $this->assertSame('record', $command->getName());
    }

    public function testCommandDescription(): void
    {
        $command = $this->app->find('record');
        $this->assertNotEmpty($command->getDescription());
    }

    public function testRequiredEvidenceArgument(): void
    {
        $command = $this->app->find('record');
        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasArgument('evidence'));
        $this->assertTrue($definition->getArgument('evidence')->isRequired());
    }

    public function testOptionalOperationArgument(): void
    {
        $command = $this->app->find('record');
        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasArgument('operation'));
        $this->assertFalse($definition->getArgument('operation')->isRequired());
        $this->assertSame('list', $definition->getArgument('operation')->getDefault());
    }

    public function testOptionalIdArgument(): void
    {
        $command = $this->app->find('record');
        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasArgument('id'));
        $this->assertFalse($definition->getArgument('id')->isRequired());
    }

    #[DataProvider('optionProvider')]
    public function testOptionExists(string $name): void
    {
        $command = $this->app->find('record');
        $this->assertTrue($command->getDefinition()->hasOption($name));
    }

    public static function optionProvider(): iterable
    {
        return [
            ['columns'],
            ['limit'],
            ['start'],
            ['order'],
            ['filter'],
            ['detail'],
            ['relations'],
            ['includes'],
            ['dry-run'],
            ['add-row-count'],
            ['data'],
            ['force'],
        ];
    }

    public function testCommandIsRegisteredInApplication(): void
    {
        $this->assertTrue($this->app->has('record'));
    }
}
