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
use Symfony\Bundle\FrameworkBundle\Command\SecretsSetCommand;
use Symfony\Bundle\FrameworkBundle\Secrets\AbstractVault;
use Symfony\Component\Console\Tester\CommandCompletionTester;

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

    public static function provideCompletionSuggestions()
    {
        yield 'name' => [[''], ['SECRET', 'OTHER_SECRET']];
        yield '--local name (with local vault)' => [['--local', ''], ['SECRET', 'OTHER_SECRET']];
    }
}
