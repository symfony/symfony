<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\DumpCompletionCommand;
use Symfony\Component\Console\Tester\CommandCompletionTester;

class DumpCompletionCommandTest extends TestCase
{
    /**
     * @dataProvider provideCompletionSuggestions
     */
    public function testComplete(array $input, array $expectedSuggestions)
    {
        $tester = new CommandCompletionTester(new DumpCompletionCommand());
        $suggestions = $tester->complete($input);

        $this->assertSame($expectedSuggestions, $suggestions);
    }

    public function provideCompletionSuggestions()
    {
        yield 'shell' => [
            [''],
            ['bash'],
        ];
    }
}
