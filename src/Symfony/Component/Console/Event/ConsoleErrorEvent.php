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
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Debug\Exception\FatalThrowableError;

/**
 * Allows to handle throwables thrown while running a command.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
class ConsoleErrorEvent extends ConsoleExceptionEvent
{
    private $error;
    private $handled = false;

    public function __construct(Command $command, InputInterface $input, OutputInterface $output, $error, $exitCode)
    {
        if (!$error instanceof \Throwable && !$error instanceof \Exception) {
            throw new InvalidArgumentException(sprintf('The error passed to ConsoleErrorEvent must be an instance of \Throwable or \Exception, "%s" was passed instead.', is_object($error) ? get_class($error) : gettype($error)));
        }

        $exception = $error;
        if (!$error instanceof \Exception) {
            $exception = new FatalThrowableError($error);
        }
        parent::__construct($command, $input, $output, $exception, $exitCode, false);

        $this->error = $error;
    }

    /**
     * Returns the thrown error/exception.
     *
     * @return \Throwable
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Replaces the thrown error/exception.
     *
     * @param \Throwable $error
     */
    public function setError($error)
    {
        if (!$error instanceof \Throwable && !$error instanceof \Exception) {
            throw new InvalidArgumentException(sprintf('The error passed to ConsoleErrorEvent must be an instance of \Throwable or \Exception, "%s" was passed instead.', is_object($error) ? get_class($error) : gettype($error)));
        }

        $this->error = $error;
    }

    /**
     * Marks the error/exception as handled.
     *
     * If it is not marked as handled, the error/exception will be displayed in
     * the command output.
     */
    public function markErrorAsHandled()
    {
        $this->handled = true;
    }

    /**
     * Whether the error/exception is handled by a listener or not.
     *
     * If it is not yet handled, the error/exception will be displayed in the
     * command output.
     *
     * @return bool
     */
    public function isErrorHandled()
    {
        return $this->handled;
    }

    /**
     * @deprecated Since version 3.3, to be removed in 4.0. Use getError() instead
     */
    public function getException()
    {
        @trigger_error(sprintf('The %s() method is deprecated since version 3.3 and will be removed in 4.0. Use ConsoleErrorEvent::getError() instead.', __METHOD__), E_USER_DEPRECATED);

        return parent::getException();
    }

    /**
     * @deprecated Since version 3.3, to be removed in 4.0. Use setError() instead
     */
    public function setException(\Exception $exception)
    {
        @trigger_error(sprintf('The %s() method is deprecated since version 3.3 and will be removed in 4.0. Use ConsoleErrorEvent::setError() instead.', __METHOD__), E_USER_DEPRECATED);

        parent::setException($exception);
    }
}
