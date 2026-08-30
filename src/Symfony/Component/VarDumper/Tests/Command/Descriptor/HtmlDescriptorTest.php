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
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Command\Descriptor\HtmlDescriptor;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;

class HtmlDescriptorTest extends TestCase
{
    private static string $timezone;

    public static function setUpBeforeClass(): void
    {
        self::$timezone = date_default_timezone_get();
        date_default_timezone_set('UTC');
    }

    public static function tearDownAfterClass(): void
    {
        date_default_timezone_set(self::$timezone);
    }

    public function testItOutputsStylesAndScriptsOnFirstDescribeCall()
    {
        $output = new BufferedOutput();
        $dumper = $this->createStub(HtmlDumper::class);
        $dumper->method('dump')->willReturn('[DUMPED]');
        $descriptor = new HtmlDescriptor($dumper);

        $descriptor->describe($output, new Data([[123]]), ['timestamp' => 1544804268.3668], 1);

        $this->assertStringMatchesFormat('<style>%A</style><script>%A</script>%A', $output->fetch(), 'styles & scripts are output');

        $descriptor->describe($output, new Data([[123]]), ['timestamp' => 1544804268.3668], 1);

        $this->assertDoesNotMatchRegularExpression('#<style>(.*?)</style><script>(.*?)</script>(.*)#', $output->fetch(), 'styles & scripts are output only once');
    }

    public function testItEscapesContextStrings()
    {
        $output = new BufferedOutput();
        $dumper = $this->createStub(HtmlDumper::class);
        $dumper->method('dump')->willReturn('[DUMPED]');
        $descriptor = new HtmlDescriptor($dumper);

        $descriptor->describe($output, new Data([[123]]), ['timestamp' => 1544804268.3668], 1);
        $output->fetch();

        $descriptor->describe($output, new Data([[123]]), [
            'timestamp' => 1544804268.3668,
            'request' => [
                'identifier' => 'id"><script>alert(1)</script>',
                'controller' => new Data([['FooController.php']]),
                'method' => 'GET<script>alert(2)</script>',
                'uri' => 'http://localhost/"><script>alert(3)</script>',
            ],
            'source' => [
                'name' => 'Foo<script>alert(4)</script>.php',
                'line' => 30,
                'project_dir' => '/app"><script>alert(5)</script>',
                'file_link' => 'phpstorm://open"><script>alert(6)</script>',
            ],
        ], 1);

        $dump = $output->fetch();

        $this->assertStringNotContainsString('<script>', $dump);
        $this->assertStringContainsString('data-dedup-id="id&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', $dump);
        $this->assertStringContainsString('<code>GET&lt;script&gt;alert(2)&lt;/script&gt;</code>', $dump);
        $this->assertStringContainsString('<a href="http://localhost/&quot;&gt;&lt;script&gt;alert(3)&lt;/script&gt;">http://localhost/&quot;&gt;&lt;script&gt;alert(3)&lt;/script&gt;</a>', $dump);
        $this->assertStringContainsString('<a href="phpstorm://open&quot;&gt;&lt;script&gt;alert(6)&lt;/script&gt;">Foo&lt;script&gt;alert(4)&lt;/script&gt;.php on line 30</a>', $dump);
        $this->assertStringContainsString('<li><span class="badge">project dir</span>/app&quot;&gt;&lt;script&gt;alert(5)&lt;/script&gt;</li>', $dump);
    }

    public function testItKeepsContextStringsThatAreNotValidUtf8()
    {
        $output = new BufferedOutput();
        $dumper = $this->createStub(HtmlDumper::class);
        $dumper->method('dump')->willReturn('[DUMPED]');
        $descriptor = new HtmlDescriptor($dumper);

        $descriptor->describe($output, new Data([[123]]), [
            'timestamp' => 1544804268.3668,
            'cli' => [
                'identifier' => 'd8bece1c',
                'command_line' => "bin/phpunit \xB1",
            ],
        ], 1);

        $this->assertStringContainsString('<code>$ </code>bin/phpunit ', $output->fetch());
    }

    public function testItKeepsTheDumpedControllerAsMarkup()
    {
        $output = new BufferedOutput();
        $dumper = $this->createStub(HtmlDumper::class);
        $dumper->method('dump')->willReturn('<span class=sf-dump-str>App\\Controller\\FooController</span>');
        $descriptor = new HtmlDescriptor($dumper);

        $descriptor->describe($output, new Data([[123]]), [
            'timestamp' => 1544804268.3668,
            'request' => [
                'identifier' => 'd8bece1c',
                'controller' => new Data([['FooController.php']]),
                'method' => 'GET',
                'uri' => 'http://localhost/foo',
            ],
        ], 1);

        $this->assertStringContainsString('<span class=\'dumped-tag\'><span class=sf-dump-str>App\\Controller\\FooController</span></span>', $output->fetch());
    }

    #[DataProvider('provideContext')]
    public function testDescribe(array $context, string $expectedOutput)
    {
        $output = new BufferedOutput();
        $dumper = $this->createStub(HtmlDumper::class);
        $dumper->method('dump')->willReturn('[DUMPED]');
        $descriptor = new HtmlDescriptor($dumper);

        $descriptor->describe($output, new Data([[123]]), $context + ['timestamp' => 1544804268.3668], 1);

        $this->assertStringMatchesFormat(trim($expectedOutput), trim(preg_replace('@<style>.*</style><script>.*</script>@s', '', $output->fetch())));
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
                <article data-dedup-id="%s">
                    <header>
                        <div class="row">
                            <h2 class="col">-</h2>
                            <time class="col text-small" title="2018-12-14T16:17:48+00:00" datetime="2018-12-14T16:17:48+00:00">
                                Fri, 14 Dec 2018 16:17:48 +0000
                            </time>
                        </div>
                        
                    </header>
                    <section class="body">
                        <p class="text-small">
                            CliDescriptorTest.php on line 30
                        </p>
                        [DUMPED]
                    </section>
                </article>
                TXT,
        ];

        yield 'source full' => [
            [
                'source' => [
                    'name' => 'CliDescriptorTest.php',
                    'project_dir' => 'src/Symfony/',
                    'line' => 30,
                    'file_relative' => 'src/Symfony/Component/VarDumper/Tests/Command/Descriptor/CliDescriptorTest.php',
                    'file' => '/Users/ogi/symfony/src/Symfony/Component/VarDumper/Tests/Command/Descriptor/CliDescriptorTest.php',
                    'file_link' => 'phpstorm://open?file=/Users/ogi/symfony/src/Symfony/Component/VarDumper/Tests/Command/Descriptor/CliDescriptorTest.php&line=30',
                ],
            ],
            <<<TXT
                <article data-dedup-id="%s">
                    <header>
                        <div class="row">
                            <h2 class="col">-</h2>
                            <time class="col text-small" title="2018-12-14T16:17:48+00:00" datetime="2018-12-14T16:17:48+00:00">
                                Fri, 14 Dec 2018 16:17:48 +0000
                            </time>
                        </div>
                        <div class="row">
                    <ul class="tags">
                        <li><span class="badge">project dir</span>src/Symfony/</li>
                    </ul>
                </div>
                    </header>
                    <section class="body">
                        <p class="text-small">
                            <a href="phpstorm://open?file=/Users/ogi/symfony/src/Symfony/Component/VarDumper/Tests/Command/Descriptor/CliDescriptorTest.php&amp;line=30">CliDescriptorTest.php on line 30</a>
                        </p>
                        [DUMPED]
                    </section>
                </article>
                TXT,
        ];

        yield 'cli' => [
            [
                'cli' => [
                    'identifier' => 'd8bece1c',
                    'command_line' => 'bin/phpunit',
                ],
            ],
            <<<TXT
                <article data-dedup-id="d8bece1c">
                    <header>
                        <div class="row">
                            <h2 class="col"><code>$ </code>bin/phpunit</h2>
                            <time class="col text-small" title="2018-12-14T16:17:48+00:00" datetime="2018-12-14T16:17:48+00:00">
                                Fri, 14 Dec 2018 16:17:48 +0000
                            </time>
                        </div>
                        
                    </header>
                    <section class="body">
                        <p class="text-small">
                            
                        </p>
                        [DUMPED]
                    </section>
                </article>
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
                <article data-dedup-id="d8bece1c">
                    <header>
                        <div class="row">
                            <h2 class="col"><code>GET</code> <a href="http://localhost/foo">http://localhost/foo</a></h2>
                            <time class="col text-small" title="2018-12-14T16:17:48+00:00" datetime="2018-12-14T16:17:48+00:00">
                                Fri, 14 Dec 2018 16:17:48 +0000
                            </time>
                        </div>
                        <div class="row">
                    <ul class="tags">
                        <li><span class="badge">controller</span><span class='dumped-tag'>[DUMPED]</span></li>
                    </ul>
                </div>
                    </header>
                    <section class="body">
                        <p class="text-small">
                            
                        </p>
                        [DUMPED]
                    </section>
                </article>
                TXT,
        ];
    }
}
