<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Command\SecretsRemoveCommand;
use Symfony\Bundle\FrameworkBundle\Secrets\AbstractVault;
use Symfony\Component\Console\Tester\CommandCompletionTester;

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

    public static function provideCompletionSuggestions()
    {
        yield 'name' => [true, [''], ['SECRET', 'OTHER_SECRET']];
        yield '--local name (with local vault)' => [true, ['--local', ''], ['SECRET']];
        yield '--local name (without local vault)' => [false, ['--local', ''], []];
    }
}
