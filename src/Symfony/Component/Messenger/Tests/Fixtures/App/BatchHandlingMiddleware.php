<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Fixtures\App;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\FlushBatchHandlersStamp;
use Symfony\Component\Messenger\Tests\Fixtures\ChildDummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\SecondMessage;
use Symfony\Component\Messenger\Tests\Fixtures\ThirdMessage;

final class BatchHandlingMiddleware implements MiddlewareInterface
{
    public static array $log = [];

    private readonly HandleMessageMiddleware $middleware;

    public function __construct()
    {
        $this->middleware = new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [new HandlerDescriptor(new BatchSizeReportingHandler())],
            ChildDummyMessage::class => [new HandlerDescriptor(new BatchSizeReportingHandler(), ['alias' => 'second'])],
            SecondMessage::class => [new HandlerDescriptor(static function (): never {
                throw new \RuntimeException('Expected handler failure.');
            })],
            ThirdMessage::class => [new HandlerDescriptor(static fn (): string => 'third')],
        ]));
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();
        $name = $envelope->last(FlushBatchHandlersStamp::class) ? 'flush' : ($message instanceof DummyMessage ? $message->getMessage() : get_debug_type($message));
        self::$log[] = 'begin '.$name;

        try {
            return $this->middleware->handle($envelope, $stack);
        } finally {
            self::$log[] = 'end '.$name;
        }
    }
}
