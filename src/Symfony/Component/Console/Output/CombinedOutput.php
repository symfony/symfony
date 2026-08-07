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
use Symfony\Component\Console\Formatter\OutputFormatterInterface;

/**
 * @internal
 */
final class CombinedOutput implements OutputInterface
{
    /**
     * @param OutputInterface[] $outputs
     */
    public function __construct(
        private array $outputs,
    ) {
        if (!$outputs) {
            throw new LogicException('Expected at least one output.');
        }
    }

    public function write(iterable|string $messages, bool $newline = false, int $options = 0): void
    {
        $args = \func_get_args();
        $args[0] = $this->buffer($messages, $options);

        foreach ($this->outputs as $output) {
            $output->write(...$args);
        }
    }

    public function writeln(iterable|string $messages, int $options = 0): void
    {
        $args = \func_get_args();
        $args[0] = $this->buffer($messages, $options);

        foreach ($this->outputs as $output) {
            $output->writeln(...$args);
        }
    }

    public function setVerbosity(int $level): void
    {
        foreach ($this->outputs as $output) {
            $output->setVerbosity($level);
        }
    }

    public function getVerbosity(): int
    {
        return array_first($this->outputs)->getVerbosity();
    }

    public function isSilent(): bool
    {
        return array_first($this->outputs)->isSilent();
    }

    public function isQuiet(): bool
    {
        return array_first($this->outputs)->isQuiet();
    }

    public function isVerbose(): bool
    {
        return array_first($this->outputs)->isVerbose();
    }

    public function isVeryVerbose(): bool
    {
        return array_first($this->outputs)->isVeryVerbose();
    }

    public function isDebug(): bool
    {
        return array_first($this->outputs)->isDebug();
    }

    public function setDecorated(bool $decorated): void
    {
        foreach ($this->outputs as $output) {
            $output->setDecorated($decorated);
        }
    }

    public function isDecorated(): bool
    {
        return array_first($this->outputs)->isDecorated();
    }

    public function setFormatter(OutputFormatterInterface $formatter): void
    {
        foreach ($this->outputs as $output) {
            $output->setFormatter($formatter);
        }
    }

    public function getFormatter(): OutputFormatterInterface
    {
        return array_first($this->outputs)->getFormatter();
    }

    /**
     * Buffers one-shot iterables, as writing them to the first output would otherwise
     * leave nothing to write to the next ones. Iterables that no output would write
     * are not consumed at all.
     */
    private function buffer(iterable|string $messages, int $options): iterable|string
    {
        if (!$messages instanceof \Traversable) {
            return $messages;
        }

        $verbosities = self::VERBOSITY_QUIET | self::VERBOSITY_NORMAL | self::VERBOSITY_VERBOSE | self::VERBOSITY_VERY_VERBOSE | self::VERBOSITY_DEBUG;
        $verbosity = $verbosities & $options ?: self::VERBOSITY_NORMAL;

        foreach ($this->outputs as $output) {
            if ($verbosity <= $output->getVerbosity()) {
                return iterator_to_array($messages, false);
            }
        }

        return [];
    }
}
