<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Command\Descriptor;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Command\Descriptor\CliDescriptor;
use Symfony\Component\VarDumper\Dumper\CliDumper;

class CliDescriptorTest extends TestCase
{
    private static string $timezone;
    private static string|false $prevTerminalEmulator;

    public static function setUpBeforeClass(): void
    {
        self::$timezone = date_default_timezone_get();
        date_default_timezone_set('UTC');

        self::$prevTerminalEmulator = getenv('TERMINAL_EMULATOR');
        putenv('TERMINAL_EMULATOR');
    }

    public static function tearDownAfterClass(): void
    {
        date_default_timezone_set(self::$timezone);
        putenv('TERMINAL_EMULATOR'.(self::$prevTerminalEmulator ? '='.self::$prevTerminalEmulator : ''));
    }

    public function testItEscapesContextStrings()
    {
        $output = new BufferedOutput();
        $descriptor = new CliDescriptor(new CliDumper(static fn ($s) => $s));

        $descriptor->describe($output, new Data([[123]]), [
            'timestamp' => 1544804268.3668,
            'request' => [
                'identifier' => 'd8bece1c',
                'controller' => new Data([['FooController.php']]),
                'method' => 'GET',
                'uri' => "http://localhost/<comment>pwned</>\033[2J\u{9b}2J",
            ],
            'source' => [
                'name' => '<href=http://evil.example>Foo.php',
                'line' => 30,
                'file' => "/app/<info>Foo</info>.php\033[2J",
            ],
        ], 1);

        $dump = $output->fetch();

        $this->assertStringNotContainsString("\033", $dump);
        $this->assertStringNotContainsString("\u{9b}", $dump);
        $this->assertStringContainsString('GET http://localhost/<comment>pwned</>', $dump);
        $this->assertStringContainsString('<href=http://evil.example>Foo.php on line 30', $dump);
        $this->assertStringContainsString('/app/<info>Foo</info>.php', $dump);
    }

    public function testItEscapesTheDumpedController()
    {
        $output = new BufferedOutput();
        $output->setDecorated(true);
        $descriptor = new CliDescriptor(new CliDumper(static fn ($s) => $s));

        $descriptor->describe($output, new Data([[123]]), [
            'timestamp' => 1544804268.3668,
            'request' => [
                'identifier' => 'd8bece1c',
                'controller' => new Data([["<fg=red>pwned</>\033[2J"]]),
                'method' => 'GET',
                'uri' => 'http://localhost/',
            ],
        ], 1);

        $dump = $output->fetch();

        $this->assertStringNotContainsString("\033[31m", $dump);
        $this->assertStringNotContainsString("\033[2J", $dump);
        $this->assertStringContainsString('<fg=red>pwned</>', $dump);
    }

    public function testItEscapesTheFileLink()
    {
        if ('\<\>' !== OutputFormatter::escape('<>')) {
            $this->markTestSkipped('The installed symfony/console does not escape adjacent brackets.');
        }

        $output = new BufferedOutput();
        $output->setDecorated(true);
        $descriptor = new CliDescriptor(new CliDumper(static fn ($s) => $s));

        $descriptor->describe($output, new Data([[123]]), [
            'timestamp' => 1544804268.3668,
            'source' => [
                'name' => 'Foo.php',
                'line' => 30,
                'file_relative' => 'src/Foo.php',
                'file_link' => 'phpstorm://open?file=/app/Foo.php><fg=red>',
            ],
        ], 1);

        $dump = $output->fetch();

        $this->assertStringContainsString("\033]8;;phpstorm://open?file=/app/Foo.php><fg=red>\033\\Foo.php on line 30\033]8;;\033\\", $dump);
        $this->assertStringNotContainsString("\033[31m", $dump);
    }

    #[DataProvider('provideContext')]
    public function testDescribe(array $context, string $expectedOutput, bool $decorated = false)
    {
        $output = new BufferedOutput();
        $output->setDecorated($decorated);
        $descriptor = new CliDescriptor(new CliDumper(static fn ($s) => $s));

        $descriptor->describe($output, new Data([[123]]), $context + ['timestamp' => 1544804268.3668], 1);

        $this->assertStringMatchesFormat(trim($expectedOutput), str_replace(\PHP_EOL, "\n", trim($output->fetch())));
    }

    public static function provideContext()
    {
        yield 'source' => [
            [
                'source' => [
                    'name' => 'CliDescriptorTest.php',
                    'line' => 30,
                    'file' => '/Users/ogi/symfony/src/Symfony/Component/VarDumper/Tests/Command/Descriptor/CliDescriptorTest.php',
                ],
            ],
            <<<TXT
                Received from client #1
                -----------------------

                 -------- --------------------------------------------------------------------------------------------------- 
                  date     Fri, 14 Dec 2018 16:17:48 +0000                                                                    
                  source   CliDescriptorTest.php on line 30                                                                   
                  file     /Users/ogi/symfony/src/Symfony/Component/VarDumper/Tests/Command/Descriptor/CliDescriptorTest.php  
                 -------- ---------------------------------------------------------------------------------------------------
                TXT,
        ];

        yield 'source full' => [
            [
                'source' => [
                    'name' => 'CliDescriptorTest.php',
                    'line' => 30,
                    'file_relative' => 'src/Symfony/Component/VarDumper/Tests/Command/Descriptor/CliDescriptorTest.php',
                    'file' => '/Users/ogi/symfony/src/Symfony/Component/VarDumper/Tests/Command/Descriptor/CliDescriptorTest.php',
                    'file_link' => 'phpstorm://open?file=/Users/ogi/symfony/src/Symfony/Component/VarDumper/Tests/Command/Descriptor/CliDescriptorTest.php&line=30',
                ],
            ],
            <<<TXT
                Received from client #1
                -----------------------

                 -------- -------------------------------------------------------------------------------- 
                  date     Fri, 14 Dec 2018 16:17:48 +0000                                                 
                  source   CliDescriptorTest.php on line 30                                                
                  file     src/Symfony/Component/VarDumper/Tests/Command/Descriptor/CliDescriptorTest.php  
                 -------- -------------------------------------------------------------------------------- 

                TXT,
        ];

        yield 'source with hyperlink' => [
            [
                'source' => [
                    'name' => 'CliDescriptorTest.php',
                    'line' => 30,
                    'file_relative' => 'src/Symfony/Component/VarDumper/Tests/Command/Descriptor/CliDescriptorTest.php',
                    'file_link' => 'phpstorm://open?file=/Users/ogi/symfony/src/Symfony/Component/VarDumper/Tests/Command/Descriptor/CliDescriptorTest.php&line=30',
                ],
            ],
            <<<TXT
                %A
                  source   \033]8;;phpstorm://open?file=/Users/ogi/symfony/src/Symfony/Component/VarDumper/Tests/Command/Descriptor/CliDescriptorTest.php&line=30\033\CliDescriptorTest.php on line 30\033]8;;\033%A
                %A
                TXT,
            true,
        ];

        yield 'cli' => [
            [
                'cli' => [
                    'identifier' => 'd8bece1c',
                    'command_line' => 'bin/phpunit',
                ],
            ],
            <<<TXT
                $ bin/phpunit
                -------------

                 ------ --------------------------------- 
                  date   Fri, 14 Dec 2018 16:17:48 +0000  
                 ------ ---------------------------------
                TXT,
        ];

        yield 'request' => [
            [
                'request' => [
                    'identifier' => 'd8bece1c',
                    'controller' => new Data([['FooController.php']]),
                    'method' => 'GET',
                    'uri' => 'http://localhost/foo',
                ],
            ],
            <<<TXT
                GET http://localhost/foo
                ------------------------

                 ------------ --------------------------------- 
                  date         Fri, 14 Dec 2018 16:17:48 +0000  
                  controller   "FooController.php"              
                 ------------ --------------------------------- 
                TXT,
        ];
    }
}
