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
use Symfony\Component\Messenger\Envelope;

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
    public function handle(Envelope $envelope, callable $next): void
    {
        $message = $envelope->getMessage();
        $context = array(
            'message' => $message,
            'name' => \get_class($message),
        );
        $this->logger->debug('Starting handling message {name}', $context);

        try {
            $next($envelope);
        } catch (\Throwable $e) {
            $context['exception'] = $e;
            $this->logger->warning('An exception occurred while handling message {name}', $context);

            throw $e;
        }

        $this->logger->debug('Finished handling message {name}', $context);
    }
}
