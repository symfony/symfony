<?php

declare(strict_types=1);

namespace Symfony\Component\Secret\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Secret\AbstractVault;
use Symfony\Component\Secret\Command\SecretsRemoveCommand;

class SecretsRemoveCommandTest extends TestCase
{
    /**
     * @dataProvider provideCompletionSuggestions
     */
    public function testComplete(bool $withLocalVault, array $input, array $expectedSuggestions)
    {
        $vault = $this->createMock(AbstractVault::class);
        $vault->method('list')->willReturn(['SECRET' => null, 'OTHER_SECRET' => null]);
        if ($withLocalVault) {
            $localVault = $this->createMock(AbstractVault::class);
            $localVault->method('list')->willReturn(['SECRET' => null]);
        } else {
            $localVault = null;
        }
        $command = new SecretsRemoveCommand($vault, $localVault);
        $tester = new CommandCompletionTester($command);
        $suggestions = $tester->complete($input);
        $this->assertSame($expectedSuggestions, $suggestions);
    }

    public function provideCompletionSuggestions()
    {
        yield 'name' => [true, [''], ['SECRET', 'OTHER_SECRET']];
        yield '--local name (with local vault)' => [true, ['--local', ''], ['SECRET']];
        yield '--local name (without local vault)' => [false, ['--local', ''], []];
    }
}
