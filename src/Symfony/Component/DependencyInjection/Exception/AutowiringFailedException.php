<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Exception;

/**
 * Thrown when a definition cannot be autowired.
 */
class AutowiringFailedException extends RuntimeException
{
    private string $serviceId;
    private ?\Closure $messageCallback = null;

    public function __construct(string $serviceId, string|\Closure $message = '', int $code = 0, \Throwable $previous = null)
    {
        $this->serviceId = $serviceId;

        if ($message instanceof \Closure
            && (\function_exists('xdebug_is_enabled') ? xdebug_is_enabled() : \function_exists('xdebug_info'))
        ) {
            $message = $message();
        }

        if (!$message instanceof \Closure) {
            parent::__construct($message, $code, $previous);

            return;
        }

        $this->messageCallback = $message;
        parent::__construct('', $code, $previous);

        $this->message = new class($this->message, $this->messageCallback) {
            private string|self $message;
            private ?\Closure $messageCallback;

            public function __construct(&$message, &$messageCallback)
            {
                $this->message = &$message;
                $this->messageCallback = &$messageCallback;
            }

            public function __toString(): string
            {
                $messageCallback = $this->messageCallback;
                $this->messageCallback = null;

                try {
                    return $this->message = $messageCallback();
                } catch (\Throwable $e) {
                    return $this->message = $e->getMessage();
                }
            }
        };
    }

    public function getMessageCallback(): ?\Closure
    {
        return $this->messageCallback;
    }

    public function getServiceId(): string
    {
        return $this->serviceId;
    }
}
