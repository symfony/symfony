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
        foreach ($this->outputs as $output) {
            $output->write(...\func_get_args());
        }
    }

    public function writeln(iterable|string $messages, int $options = 0): void
    {
        foreach ($this->outputs as $output) {
            $output->writeln(...\func_get_args());
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
}
