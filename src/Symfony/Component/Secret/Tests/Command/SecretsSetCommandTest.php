<?php

declare(strict_types=1);

namespace Symfony\Component\Secret\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Secret\AbstractVault;
use Symfony\Component\Secret\Command\SecretsSetCommand;

class SecretsSetCommandTest extends TestCase
{
    /**
     * @dataProvider provideCompletionSuggestions
     */
    public function testComplete(array $input, array $expectedSuggestions)
    {
        $vault = $this->createMock(AbstractVault::class);
        $vault->method('list')->willReturn(['SECRET' => null, 'OTHER_SECRET' => null]);
        $localVault = $this->createMock(AbstractVault::class);
        $command = new SecretsSetCommand($vault, $localVault);
        $tester = new CommandCompletionTester($command);
        $suggestions = $tester->complete($input);
        $this->assertSame($expectedSuggestions, $suggestions);
    }

    public function provideCompletionSuggestions()
    {
        yield 'name' => [[''], ['SECRET', 'OTHER_SECRET']];
        yield '--local name (with local vault)' => [['--local', ''], ['SECRET', 'OTHER_SECRET']];
    }
}
