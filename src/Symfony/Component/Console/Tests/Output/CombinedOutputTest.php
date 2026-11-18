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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\CombinedOutput;
use Symfony\Component\Console\Output\OutputInterface;

class CombinedOutputTest extends TestCase
{
    public function testConstructorRequiresAtLeastOneOutput()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Expected at least one output.');

        new CombinedOutput([]);
    }

    public function testWriteToAllOutputs()
    {
        $output = new CombinedOutput([$first = new BufferedOutput(), $second = new BufferedOutput()]);

        $output->write('foo');
        $output->write(['bar', 'baz'], true);
        $output->writeln('qux');

        $this->assertSame('foobar'.\PHP_EOL.'baz'.\PHP_EOL.'qux'.\PHP_EOL, $first->fetch());
        $this->assertSame('foobar'.\PHP_EOL.'baz'.\PHP_EOL.'qux'.\PHP_EOL, $second->fetch());
    }

    public function testWriteForwardsOptions()
    {
        $output = new CombinedOutput([$first = new BufferedOutput(), $second = new BufferedOutput()]);

        $output->writeln('<info>foo</info>', OutputInterface::OUTPUT_RAW);
        $output->write('<info>bar</info>', false, OutputInterface::OUTPUT_RAW);

        $this->assertSame('<info>foo</info>'.\PHP_EOL.'<info>bar</info>', $first->fetch());
        $this->assertSame('<info>foo</info>'.\PHP_EOL.'<info>bar</info>', $second->fetch());
    }

    public function testWriteRespectsTheVerbosityOfEachOutput()
    {
        $output = new CombinedOutput([$normal = new BufferedOutput(), $verbose = new BufferedOutput(OutputInterface::VERBOSITY_VERBOSE)]);

        $output->writeln('normal');
        $output->writeln('verbose', OutputInterface::VERBOSITY_VERBOSE);

        $this->assertSame('normal'.\PHP_EOL, $normal->fetch());
        $this->assertSame('normal'.\PHP_EOL.'verbose'.\PHP_EOL, $verbose->fetch());
    }

    public function testWriteIterableToAllOutputs()
    {
        $output = new CombinedOutput([$first = new BufferedOutput(), $second = new BufferedOutput()]);

        $messages = static function () {
            yield 'foo';
            yield 'bar';
        };

        $output->write($messages());
        $output->writeln($messages());

        $this->assertSame('foobarfoo'.\PHP_EOL.'bar'.\PHP_EOL, $first->fetch());
        $this->assertSame('foobarfoo'.\PHP_EOL.'bar'.\PHP_EOL, $second->fetch());
    }

    public function testWriteIterableWithDuplicateKeysToAllOutputs()
    {
        $output = new CombinedOutput([$first = new BufferedOutput(), $second = new BufferedOutput()]);

        $output->writeln((static function () {
            yield 'key' => 'foo';
            yield 'key' => 'bar';
        })());

        $this->assertSame('foo'.\PHP_EOL.'bar'.\PHP_EOL, $first->fetch());
        $this->assertSame('foo'.\PHP_EOL.'bar'.\PHP_EOL, $second->fetch());
    }

    public function testWriteDoesNotConsumeIterableWhenNoOutputDisplaysIt()
    {
        $consumed = false;
        $messages = (static function () use (&$consumed) {
            $consumed = true;

            yield 'foo';
        })();

        $output = new CombinedOutput([$first = new BufferedOutput(), $second = new BufferedOutput(OutputInterface::VERBOSITY_QUIET)]);
        $output->writeln($messages, OutputInterface::VERBOSITY_VERBOSE);

        $this->assertFalse($consumed);
        $this->assertSame('', $first->fetch());
        $this->assertSame('', $second->fetch());
    }

    public function testWriteIterableWithVerbosityDisplayedByOneOutput()
    {
        $output = new CombinedOutput([$normal = new BufferedOutput(), $debug = new BufferedOutput(OutputInterface::VERBOSITY_DEBUG)]);

        $output->writeln(new \ArrayIterator(['foo', 'bar']), OutputInterface::VERBOSITY_DEBUG);

        $this->assertSame('', $normal->fetch());
        $this->assertSame('foo'.\PHP_EOL.'bar'.\PHP_EOL, $debug->fetch());
    }

    public function testSetVerbosityOnAllOutputs()
    {
        $output = new CombinedOutput([$first = new BufferedOutput(), $second = new BufferedOutput()]);

        $output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);

        $this->assertSame(OutputInterface::VERBOSITY_DEBUG, $first->getVerbosity());
        $this->assertSame(OutputInterface::VERBOSITY_DEBUG, $second->getVerbosity());
        $this->assertSame(OutputInterface::VERBOSITY_DEBUG, $output->getVerbosity());
    }

    #[DataProvider('provideVerbosities')]
    public function testVerbosityGettersReadTheFirstOutput(int $verbosity, bool $silent, bool $quiet, bool $verbose, bool $veryVerbose, bool $debug)
    {
        $output = new CombinedOutput([new BufferedOutput($verbosity), new BufferedOutput(OutputInterface::VERBOSITY_NORMAL)]);

        $this->assertSame($verbosity, $output->getVerbosity());
        $this->assertSame($silent, $output->isSilent());
        $this->assertSame($quiet, $output->isQuiet());
        $this->assertSame($verbose, $output->isVerbose());
        $this->assertSame($veryVerbose, $output->isVeryVerbose());
        $this->assertSame($debug, $output->isDebug());
    }

    public static function provideVerbosities(): iterable
    {
        yield 'silent' => [OutputInterface::VERBOSITY_SILENT, true, false, false, false, false];
        yield 'quiet' => [OutputInterface::VERBOSITY_QUIET, false, true, false, false, false];
        yield 'verbose' => [OutputInterface::VERBOSITY_VERBOSE, false, false, true, false, false];
        yield 'very verbose' => [OutputInterface::VERBOSITY_VERY_VERBOSE, false, false, true, true, false];
        yield 'debug' => [OutputInterface::VERBOSITY_DEBUG, false, false, true, true, true];
    }

    public function testSetDecoratedOnAllOutputs()
    {
        $output = new CombinedOutput([$first = new BufferedOutput(), $second = new BufferedOutput()]);

        $output->setDecorated(true);

        $this->assertTrue($first->isDecorated());
        $this->assertTrue($second->isDecorated());
        $this->assertTrue($output->isDecorated());
    }

    public function testSetFormatterOnAllOutputs()
    {
        $output = new CombinedOutput([$first = new BufferedOutput(), $second = new BufferedOutput()]);

        $output->setFormatter($formatter = new OutputFormatter());

        $this->assertSame($formatter, $first->getFormatter());
        $this->assertSame($formatter, $second->getFormatter());
        $this->assertSame($formatter, $output->getFormatter());
    }

    public function testDecorationAndFormatterGettersReadTheFirstOutput()
    {
        $output = new CombinedOutput([$first = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true), new BufferedOutput()]);

        $this->assertTrue($output->isDecorated());
        $this->assertSame($first->getFormatter(), $output->getFormatter());
    }
}
