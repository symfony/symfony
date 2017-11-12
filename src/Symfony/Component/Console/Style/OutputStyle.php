<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Style;

use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

/**
 * Decorates output to add console style guide helpers.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
abstract class OutputStyle implements OutputInterface, StyleInterface
{
    private $output;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * {@inheritdoc}
     */
    public function newLine($count = 1): void
    {
        $this->output->write(str_repeat(PHP_EOL, $count));
    }

    public function createProgressBar(int $max = 0): ProgressBar
    {
        return new ProgressBar($this->output, $max);
    }

    /**
     * {@inheritdoc}
     */
    public function write($messages, $newline = false, $type = self::OUTPUT_NORMAL): void
    {
        $this->output->write($messages, $newline, $type);
    }

    /**
     * {@inheritdoc}
     */
    public function writeln($messages, $type = self::OUTPUT_NORMAL): void
    {
        $this->output->writeln($messages, $type);
    }

    /**
     * {@inheritdoc}
     */
    public function setVerbosity($level): void
    {
        $this->output->setVerbosity($level);
    }

    /**
     * {@inheritdoc}
     */
    public function getVerbosity()
    {
        return $this->output->getVerbosity();
    }

    /**
     * {@inheritdoc}
     */
    public function setDecorated($decorated): void
    {
        $this->output->setDecorated($decorated);
    }

    /**
     * {@inheritdoc}
     */
    public function isDecorated()
    {
        return $this->output->isDecorated();
    }

    /**
     * {@inheritdoc}
     */
    public function setFormatter(OutputFormatterInterface $formatter): void
    {
        $this->output->setFormatter($formatter);
    }

    /**
     * {@inheritdoc}
     */
    public function getFormatter()
    {
        return $this->output->getFormatter();
    }

    /**
     * {@inheritdoc}
     */
    public function isQuiet()
    {
        return $this->output->isQuiet();
    }

    /**
     * {@inheritdoc}
     */
    public function isVerbose()
    {
        return $this->output->isVerbose();
    }

    /**
     * {@inheritdoc}
     */
    public function isVeryVerbose()
    {
        return $this->output->isVeryVerbose();
    }

    /**
     * {@inheritdoc}
     */
    public function isDebug()
    {
        return $this->output->isDebug();
    }

    protected function getErrorOutput()
    {
        if (!$this->output instanceof ConsoleOutputInterface) {
            return $this->output;
        }

        return $this->output->getErrorOutput();
    }
}
