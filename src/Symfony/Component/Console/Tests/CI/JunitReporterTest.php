<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\CI;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\CI\JunitReporter;
use Symfony\Component\Console\Output\BufferedOutput;

class JunitReporterTest extends TestCase
{
    public function testEmptyReport()
    {
        $reporter = new JunitReporter($buffer = new BufferedOutput());

        $reporter->write();

        $xml = simplexml_load_string($buffer->fetch());
        self::assertNotFalse($xml);
        self::assertSame('lint', (string) $xml->testsuite['name']);
        self::assertSame('0', (string) $xml->testsuite['tests']);
        self::assertSame('0', (string) $xml->testsuite['failures']);
    }

    public function testWritesPassedAndFailedTestCases()
    {
        $reporter = new JunitReporter($buffer = new BufferedOutput(), 'lint:yaml');

        $reporter->pass('config/services.yaml');
        $reporter->fail('config/routes.yaml', 'Unable to parse', 'Unable to parse at line 3 (near "foo").');
        $reporter->fail('templates/base.html.twig', '1 deprecation(s) found', 'Line 1: Deprecated filter', 'deprecation');

        $reporter->write();

        $expected = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <testsuites>
              <testsuite name="lint:yaml" tests="3" failures="2" errors="0">
                <testcase name="config/services.yaml" classname="lint:yaml" file="config/services.yaml"/>
                <testcase name="config/routes.yaml" classname="lint:yaml" file="config/routes.yaml">
                  <failure type="error" message="Unable to parse">Unable to parse at line 3 (near &quot;foo&quot;).</failure>
                </testcase>
                <testcase name="templates/base.html.twig" classname="lint:yaml" file="templates/base.html.twig">
                  <failure type="deprecation" message="1 deprecation(s) found">Line 1: Deprecated filter</failure>
                </testcase>
              </testsuite>
            </testsuites>
            XML;

        self::assertSame($expected.\PHP_EOL, $buffer->fetch());
    }

    public function testDetailsDefaultToTheMessage()
    {
        $reporter = new JunitReporter($buffer = new BufferedOutput());

        $reporter->fail('messages.en.xlf', 'Opening and ending tag mismatch');
        $reporter->write();

        $xml = simplexml_load_string($buffer->fetch());
        self::assertSame('Opening and ending tag mismatch', (string) $xml->testsuite->testcase->failure);
    }

    public function testEscapesValuesThatAreNotValidXml()
    {
        $reporter = new JunitReporter($buffer = new BufferedOutput());

        $reporter->fail('a&b <c>.yaml', "Unexpected \"<\"\x01 in \xB1", "line 1\nline 2");
        $reporter->write();

        $xml = simplexml_load_string($buffer->fetch());
        self::assertNotFalse($xml);
        self::assertSame('a&b <c>.yaml', (string) $xml->testsuite->testcase['file']);
        self::assertSame("Unexpected \"<\"\u{FFFD} in \u{FFFD}", (string) $xml->testsuite->testcase->failure['message']);
        self::assertSame("line 1\nline 2", (string) $xml->testsuite->testcase->failure);
    }
}
