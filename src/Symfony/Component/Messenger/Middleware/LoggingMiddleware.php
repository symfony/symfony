<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Middleware;

use Psr\Log\LoggerInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class LoggingMiddleware implements MiddlewareInterface
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function handle($message, callable $next)
    {
        $this->logger->debug('Starting handling message {class}', $this->createContext($message));

        try {
            $result = $next($message);
        } catch (\Throwable $e) {
            $this->logger->warning('An exception occurred while handling message {class}', array_merge(
                $this->createContext($message),
                array('exception' => $e)
            ));

            throw $e;
        }

        $this->logger->debug('Finished handling message {class}', $this->createContext($message));

        return $result;
    }

    private function createContext($message): array
    {
        return array(
            'message' => $message,
            'class' => \get_class($message),
        );
    }
}
