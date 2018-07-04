<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Exception;

/**
 * When handling messages, some handlers caused an exception. This exception
 * contains all those handler exceptions.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class MessageHandlingException extends \RuntimeException implements ExceptionInterface
{
    private $exceptions = array();

    public function __construct(array $exceptions)
    {
        $message = sprintf(
            "Some handlers for recorded messages threw an exception. Their messages were: \n\n%s",
            implode(", \n", array_map(function (\Throwable $e) {
                return $e->getMessage();
            }, $exceptions))
        );

        $this->exceptions = $exceptions;
        parent::__construct($message);
    }

    public function getExceptions(): array
    {
        return $this->exceptions;
    }
}
