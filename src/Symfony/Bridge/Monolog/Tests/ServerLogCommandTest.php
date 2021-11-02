<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Monolog\Command\ServerLogCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandCompletionTester;

class ServerLogCommandTest extends TestCase
{
    /**
     * @dataProvider provideCompletionSuggestions
     */
    public function testComplete(array $input, array $expectedSuggestions)
    {
        if (!class_exists(CommandCompletionTester::class)) {
            $this->markTestSkipped('Test command completion requires symfony/console 5.4+.');
        }

        $application = new Application();
        $application->add(new ServerLogCommand());

        $tester = new CommandCompletionTester($application->find('server:log'));
        $suggestions = $tester->complete($input, 2);
        $this->assertSame($expectedSuggestions, $suggestions);
    }

    public function provideCompletionSuggestions(): iterable
    {
        yield 'option --host' => [['--host', ''], [
            '0.0.0.0:9911',
            '127.0.0.1:80',
            '127.0.0.1:443',
            '127.0.0.1:8000',
            'http://0.0.0.0:9911',
            'http://127.0.0.1:80',
            'https://127.0.0.1:443',
            'http://127.0.0.1:8000',
        ]];
        yield 'option --format' => [['--format', ''], [
            '%datetime% %start_tag%%level_name%%end_tag% <comment>[%channel%]</> %message%%context%%extra%\n',
        ]];
        yield 'option --date-format' => [['--date-format', ''], [
            'H:i:s',
        ]];
    }
}
