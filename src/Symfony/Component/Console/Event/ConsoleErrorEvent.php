<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Event;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Allows to handle throwables thrown while running a command.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
final class ConsoleErrorEvent extends ConsoleEvent
{
    private $exception;
    private $exitCode;

    public function __construct(InputInterface $input, OutputInterface $output, \Throwable $exception, Command $command = null)
    {
        parent::__construct($command, $input, $output);

        $this->exception = $exception;
    }

    public function getException(): \Throwable
    {
        return $this->exception;
    }

    public function setException(\Throwable $exception): void
    {
        $this->exception = $exception;
    }

    /**
     * @deprecated since Symfony 4.4, use getException() instead
     */
    public function getError(): \Throwable
    {
        @trigger_error(sprintf('The "%s()" method is deprecated since Symfony 4.4, use "getException()" instead.', __METHOD__), E_USER_DEPRECATED);

        return $this->exception;
    }

    /**
     * @deprecated since Symfony 4.4, use setException() instead
     */
    public function setError(\Throwable $exception): void
    {
        @trigger_error(sprintf('The "%s()" method is deprecated since Symfony 4.4, use "setException()" instead.', __METHOD__), E_USER_DEPRECATED);

        $this->exception = $exception;
    }

    public function setExitCode(int $exitCode): void
    {
        $this->exitCode = $exitCode;

        $r = new \ReflectionProperty($this->exception, 'code');
        $r->setAccessible(true);
        $r->setValue($this->exception, $this->exitCode);
    }

    public function getExitCode(): int
    {
        return null !== $this->exitCode ? $this->exitCode : (\is_int($this->exception->getCode()) && 0 !== $this->exception->getCode() ? $this->exception->getCode() : 1);
    }
}
