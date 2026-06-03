<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Output;

use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;

/**
 * @internal
 *
 * @author Théo FIDRY <theo.fidry@gmail.com>
 */
final class TestOutput implements ConsoleOutputInterface
{
    private OutputInterface $innerOutput;
    private OutputInterface $innerErrorOutput;
    private OutputInterface $displayOutput;
    private CombinedOutput $output;
    private CombinedOutput $errorOutput;
    private OutputFormatterInterface $formatter;

    /**
     * @param OutputInterface::VERBOSITY_* $verbosity
     */
    public function __construct(
        private bool $decorated,
        private int $verbosity,
        ?OutputFormatterInterface $formatter = null,
    ) {
        $this->formatter = $formatter ?? new OutputFormatter($decorated);
        $this->formatter->setDecorated($decorated);

        $this->innerOutput = self::createOutput($this);
        $this->innerErrorOutput = self::createOutput($this);
        $this->displayOutput = self::createOutput($this);

        $this->output = new CombinedOutput([
            $this->innerOutput,
            $this->displayOutput,
        ]);
        $this->errorOutput = new CombinedOutput([
            $this->innerErrorOutput,
            $this->displayOutput,
        ]);
    }

    public function getOutputContents(): string
    {
        return $this->getStreamContents($this->innerOutput);
    }

    public function getErrorOutputContents(): string
    {
        return $this->getStreamContents($this->innerErrorOutput);
    }

    public function getDisplayContents(): string
    {
        return $this->getStreamContents($this->displayOutput);
    }

    public function getErrorOutput(): OutputInterface
    {
        return $this->errorOutput;
    }

    public function setErrorOutput(OutputInterface $error): void
    {
        throw new LogicException('TestOutput does not support modifying the error output.');
    }

    public function section(): ConsoleSectionOutput
    {
        throw new LogicException('ConsoleSectionOutput is not supported by TestOutput.');
    }

    public function write(iterable|string $messages, bool $newline = false, int $options = 0): void
    {
        $this->output->write(...\func_get_args());
    }

    public function writeln(iterable|string $messages, int $options = 0): void
    {
        $this->output->writeln(...\func_get_args());
    }

    public function setVerbosity(int $level): void
    {
        throw new LogicException('TestOutput does not support modifying the verbosity.');
    }

    public function getVerbosity(): int
    {
        return $this->verbosity;
    }

    public function isSilent(): bool
    {
        return self::VERBOSITY_SILENT === $this->verbosity;
    }

    public function isQuiet(): bool
    {
        return self::VERBOSITY_QUIET === $this->verbosity;
    }

    public function isVerbose(): bool
    {
        return self::VERBOSITY_VERBOSE <= $this->verbosity;
    }

    public function isVeryVerbose(): bool
    {
        return self::VERBOSITY_VERY_VERBOSE <= $this->verbosity;
    }

    public function isDebug(): bool
    {
        return self::VERBOSITY_DEBUG <= $this->verbosity;
    }

    public function setDecorated(bool $decorated): void
    {
        throw new LogicException('TestOutput does not support modifying the decorated flag.');
    }

    public function isDecorated(): bool
    {
        return $this->decorated;
    }

    public function setFormatter(OutputFormatterInterface $formatter): void
    {
        throw new LogicException('TestOutput does not support modifying the formatter.');
    }

    public function getFormatter(): OutputFormatterInterface
    {
        return $this->formatter;
    }

    private static function createOutput(OutputInterface $config): StreamOutput
    {
        if (false === $stream = fopen('php://memory', 'w')) {
            throw new RuntimeException('Failed to open stream.');
        }

        return new StreamOutput($stream, $config->getVerbosity(), $config->isDecorated(), $config->getFormatter());
    }

    private function getStreamContents(StreamOutput $output): string
    {
        $stream = $output->getStream();

        rewind($stream);

        if (false === $contents = stream_get_contents($stream)) {
            throw new RuntimeException('Failed to read stream contents.');
        }

        return $contents;
    }
}
