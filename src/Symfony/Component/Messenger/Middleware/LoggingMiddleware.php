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

@trigger_error(sprintf('The "%s" class is deprecated since Symfony 4.3, pass a logger to SendMessageMiddleware instead.', LoggingMiddleware::class), \E_USER_DEPRECATED);

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 *
 * @deprecated since 4.3, pass a logger to SendMessageMiddleware instead
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
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();
        $context = [
            'message' => $message,
            'class' => \get_class($envelope->getMessage()),
        ];
        $this->logger->debug('Starting handling message "{class}"', $context);

        try {
            $envelope = $stack->next()->handle($envelope, $stack);
        } catch (\Throwable $e) {
            $context['exception'] = $e;
            $this->logger->warning('An exception occurred while handling message "{class}": '.$e->getMessage(), $context);

            throw $e;
        }

        $this->logger->debug('Finished handling message "{class}"', $context);

        return $envelope;
    }
}
