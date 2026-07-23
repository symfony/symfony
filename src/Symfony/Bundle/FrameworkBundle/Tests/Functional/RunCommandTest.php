<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\ExecutionResult;

#[Group('functional')]
class RunCommandTest extends AbstractWebTestCase
{
    protected function setUp(): void
    {
        static::bootKernel(['test_case' => 'RunCommand', 'root_config' => 'config.yml']);
    }

    public function testRunCommandReturnsExecutionResult()
    {
        $result = static::runCommand('list');

        $this->assertInstanceOf(ExecutionResult::class, $result);
        $this->assertSame(Command::SUCCESS, $result->statusCode);
        $this->assertStringContainsString('Available commands:', $result->getOutput());
    }

    public function testRunCommandIsSuccessful()
    {
        $result = static::runCommand('list');

        $this->assertCommandIsSuccessful($result);
    }

    public function testRunCommandWithArguments()
    {
        $result = static::runCommand('list', ['namespace' => 'debug']);

        $this->assertCommandIsSuccessful($result);
        $this->assertStringContainsString('debug', $result->getOutput());
    }
}
