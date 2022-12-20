<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Output;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Formatter\NullOutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;

class NullOutputTest extends TestCase
{
    public function testConstructor()
    {
        $output = new NullOutput();

        ob_start();
        $output->write('foo');
        $buffer = ob_get_clean();

        self::assertSame('', $buffer, '->write() does nothing (at least nothing is printed)');
        self::assertFalse($output->isDecorated(), '->isDecorated() returns false');
    }

    public function testVerbosity()
    {
        $output = new NullOutput();
        self::assertSame(OutputInterface::VERBOSITY_QUIET, $output->getVerbosity(), '->getVerbosity() returns VERBOSITY_QUIET for NullOutput by default');

        $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
        self::assertSame(OutputInterface::VERBOSITY_QUIET, $output->getVerbosity(), '->getVerbosity() always returns VERBOSITY_QUIET for NullOutput');
    }

    public function testGetFormatter()
    {
        $output = new NullOutput();
        self::assertInstanceof(NullOutputFormatter::class, $formatter = $output->getFormatter());
        self::assertSame($formatter, $output->getFormatter());
    }

    public function testSetFormatter()
    {
        $output = new NullOutput();
        $outputFormatter = new OutputFormatter();
        $output->setFormatter($outputFormatter);
        self::assertNotSame($outputFormatter, $output->getFormatter());
    }

    public function testSetVerbosity()
    {
        $output = new NullOutput();
        $output->setVerbosity(Output::VERBOSITY_NORMAL);
        self::assertEquals(Output::VERBOSITY_QUIET, $output->getVerbosity());
    }

    public function testSetDecorated()
    {
        $output = new NullOutput();
        $output->setDecorated(true);
        self::assertFalse($output->isDecorated());
    }

    public function testIsQuiet()
    {
        $output = new NullOutput();
        self::assertTrue($output->isQuiet());
    }

    public function testIsVerbose()
    {
        $output = new NullOutput();
        self::assertFalse($output->isVerbose());
    }

    public function testIsVeryVerbose()
    {
        $output = new NullOutput();
        self::assertFalse($output->isVeryVerbose());
    }

    public function testIsDebug()
    {
        $output = new NullOutput();
        self::assertFalse($output->isDebug());
    }
}
