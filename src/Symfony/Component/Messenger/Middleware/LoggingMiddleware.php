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

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\SentStamp;

/**
 * Measures processing time and memory consumption, then logs whether the
 * message was handled, sent to a transport, or failed.
 *
 * Optional middleware: add it at whatever position in the stack suits
 * your logging needs.
 *
 * @author Marie Charles <marie.charles@hetic.net>
 */
class LoggingMiddleware implements MiddlewareInterface
{
    /**
     * @var \Closure(): int
     */
    private \Closure $memoryResolver;

    /**
     * @param ?(callable(): int) $memoryResolver
     */
    public function __construct(
        private LoggerInterface $logger,
        private ClockInterface $clock = new Clock(),
        ?callable $memoryResolver = null,
    ) {
        $memoryResolver ??= static fn (): int => memory_get_usage();
        $this->memoryResolver = $memoryResolver(...);
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $context = [
            'class' => get_debug_type($envelope->getMessage()),
        ];

        $memoryResolver = $this->memoryResolver;

        $startTime = (float) $this->clock->now()->format('U.u');
        $startMemory = $memoryResolver();

        $success = false;

        try {
            $envelope = $stack->next()->handle($envelope, $stack);

            $success = true;

            return $envelope;
        } catch (\Throwable $exception) {
            $context['exception'] = $exception;

            throw $exception;
        } finally {
            $endTime = (float) $this->clock->now()->format('U.u');

            $context = [
                ...$context,
                'duration_ms' => (int) round(($endTime - $startTime) * 1000),
                'memory_usage' => $memoryResolver() - $startMemory,
            ];

            if (!$success) {
                $this->logger->error('Unable to handle "{class}" message.', $context);
            } elseif ($envelope->last(SentStamp::class)) {
                $this->logger->info('"{class}" message sent to transport.', $context);
            } else {
                $this->logger->info('"{class}" message successfully handled.', $context);
            }
        }
    }
}
