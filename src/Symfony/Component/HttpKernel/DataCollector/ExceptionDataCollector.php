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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\FlattenException;

/**
 * ExceptionDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @since v2.0.0
 */
class ExceptionDataCollector extends DataCollector
{
    /**
     * {@inheritdoc}
     *
     * @since v2.0.0
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        if (null !== $exception) {
            $this->data = array(
                'exception' => FlattenException::create($exception),
            );
        }
    }

    /**
     * Checks if the exception is not null.
     *
     * @return Boolean true if the exception is not null, false otherwise
     *
     * @since v2.0.0
     */
    public function hasException()
    {
        return isset($this->data['exception']);
    }

    /**
     * Gets the exception.
     *
     * @return \Exception The exception
     *
     * @since v2.0.0
     */
    public function getException()
    {
        return $this->data['exception'];
    }

    /**
     * Gets the exception message.
     *
     * @return string The exception message
     *
     * @since v2.0.0
     */
    public function getMessage()
    {
        return $this->data['exception']->getMessage();
    }

    /**
     * Gets the exception code.
     *
     * @return integer The exception code
     *
     * @since v2.0.0
     */
    public function getCode()
    {
        return $this->data['exception']->getCode();
    }

    /**
     * Gets the status code.
     *
     * @return integer The status code
     *
     * @since v2.0.0
     */
    public function getStatusCode()
    {
        return $this->data['exception']->getStatusCode();
    }

    /**
     * Gets the exception trace.
     *
     * @return array The exception trace
     *
     * @since v2.0.0
     */
    public function getTrace()
    {
        return $this->data['exception']->getTrace();
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.0.0
     */
    public function getName()
    {
        return 'exception';
    }
}
