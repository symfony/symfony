<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\DataCollector;

use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ExceptionDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ExceptionDataCollector extends DataCollector
{
    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null): void
    {
        if (null !== $exception) {
            $this->data = array(
                'exception' => FlattenException::create($exception),
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function reset(): void
    {
        $this->data = array();
    }

    /**
     * Checks if the exception is not null.
     *
     * @return bool true if the exception is not null, false otherwise
     */
    public function hasException(): bool
    {
        return isset($this->data['exception']);
    }

    /**
     * Gets the exception.
     *
     * @return \Exception The exception
     */
    public function getException(): \Exception
    {
        return $this->data['exception'];
    }

    /**
     * Gets the exception message.
     *
     * @return string The exception message
     */
    public function getMessage(): string
    {
        return $this->data['exception']->getMessage();
    }

    /**
     * Gets the exception code.
     *
     * @return int The exception code
     */
    public function getCode(): int
    {
        return $this->data['exception']->getCode();
    }

    /**
     * Gets the status code.
     *
     * @return int The status code
     */
    public function getStatusCode(): int
    {
        return $this->data['exception']->getStatusCode();
    }

    /**
     * Gets the exception trace.
     *
     * @return array The exception trace
     */
    public function getTrace(): array
    {
        return $this->data['exception']->getTrace();
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'exception';
    }
}
