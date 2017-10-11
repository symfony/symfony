<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Command;

use Symfony\Component\Console\Exception\RuntimeException;

/**
 * This trait adds ability to interrupt safely long-running or endless commands in case "^C" is pressed or "$ kill -2".
 *
 * @author Maksym Slesarenko <maks.slesarenko@gmail.com>
 *
 * @example
 * protected function execute(InputInterface $input, OutputInterface $output)
 * {
 *     while (true) {
 *         $this->isInterrupted(true);
 *
 *         //doSomething
 *         sleep(100);
 *    }
 *    // or
 *    while (!$this->isInterrupted()) {
 *         //doSomething
 *         sleep(100);
 *    }
 * }
 */
trait InterruptibleTrait
{
    private $isInterrupted = false;

    public function __construct($name = null)
    {
        pcntl_signal(\SIGINT, function () {
            $this->isInterrupted = true;
        });
        parent::__construct($name);
    }

    /**
     * Check if command is interrupted.
     *
     * @throws RuntimeException
     *
     * @param bool $throwException
     *
     * @return bool
     */
    public function isInterrupted($throwException = false)
    {
        pcntl_signal_dispatch();

        if ($this->isInterrupted && $throwException) {
            throw new RuntimeException('Command execution was interrupted');
        }

        return $this->isInterrupted;
    }
}
