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
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Decorates output to add console style guide helpers
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
abstract class OutputStyle implements OutputInterface, StyleInterface
{
    private $output;

    /**
     * @param OutputInterface $output
     */
    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * {@inheritdoc}
     */
    public function ln($count = 1)
    {
        $this->output->write(str_repeat(PHP_EOL, $count));
    }

    /**
     * {@inheritdoc}
     */
    public function write($messages, $newline = false, $type = self::OUTPUT_NORMAL)
    {
        $this->output->write($messages, $newline, $type);
    }

    /**
     * {@inheritdoc}
     */
    public function writeln($messages, $type = self::OUTPUT_NORMAL)
    {
        $this->output->writeln($messages, $type);
    }

    /**
     * {@inheritdoc}
     */
    public function setVerbosity($level)
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
    public function setDecorated($decorated)
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
    public function setFormatter(OutputFormatterInterface $formatter)
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
}
