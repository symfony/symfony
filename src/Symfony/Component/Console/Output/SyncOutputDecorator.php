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

use Symfony\Component\Console\Formatter\OutputFormatterInterface;

/**
 * Synchronize one output with another.
 *
 * @author Vadim Zharkov <hushker@gmail.com>
 */
class SyncOutputDecorator implements OutputInterface
{
    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var OutputInterface
     */
    private $syncOutput;

    public function __construct(OutputInterface $output, OutputInterface $syncOutput)
    {
        $this->output = $output;
        $this->syncOutput = $syncOutput;
    }

    public function write($messages, bool $newline = false, int $options = 0)
    {
        $this->output->write($messages, $newline, $options);
        $this->syncOutput->write($messages, $newline, $options);
    }

    public function writeln($messages, int $options = 0)
    {
        $this->output->writeln($messages, $options);
        $this->syncOutput->writeln($messages, $options);
    }

    public function setVerbosity(int $level)
    {
        $this->output->setVerbosity($level);
        $this->syncOutput->setVerbosity($level);
    }

    public function getVerbosity()
    {
        return $this->output->getVerbosity();
    }

    public function isQuiet()
    {
        return $this->output->isQuiet();
    }

    public function isVerbose()
    {
        return $this->output->isVerbose();
    }

    public function isVeryVerbose()
    {
        return $this->output->isVeryVerbose();
    }

    public function isDebug()
    {
        return $this->output->isDebug();
    }

    public function setDecorated(bool $decorated)
    {
        $this->output->setDecorated($decorated);
        $this->syncOutput->setDecorated($decorated);
    }

    public function isDecorated()
    {
        return $this->output->isDecorated();
    }

    public function setFormatter(OutputFormatterInterface $formatter)
    {
        $this->output->setFormatter($formatter);
        $this->syncOutput->setFormatter($formatter);
    }

    public function getFormatter()
    {
        return $this->output->getFormatter();
    }
}
