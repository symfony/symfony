<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Handler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Handler\Acknowledger;
use Symfony\Component\Messenger\Handler\BatchHandlerInterface;
use Symfony\Component\Messenger\Handler\BatchHandlerTrait;

class BatchHandlerTest extends TestCase
{
    public function testHandleSynchronouslySuccessfully()
    {
        $handler = new BatchHandlerTestHandler();

        $result = $handler(new BatchHandlerTestMessage());

        self::assertSame('handler success', $result);
    }

    public function testHandleSynchronouslyWithException()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('handler error');

        $handler = new BatchHandlerTestHandler();

        $handler(new BatchHandlerTestMessage(withError: true));
    }

    public function testHandleAsynchronouslySuccessfully()
    {
        $handler = new BatchHandlerTestHandler();

        $ack = new Acknowledger($handler::class);
        $count = $handler(new BatchHandlerTestMessage(), $ack);

        self::assertSame(0, $count);
        self::assertSame('handler success', $ack->getResult());
        self::assertNull($ack->getError());
    }

    public function testHandleAsynchronouslyWithException()
    {
        $handler = new BatchHandlerTestHandler();

        $ack = new Acknowledger($handler::class);
        $count = $handler(new BatchHandlerTestMessage(withError: true), $ack);

        self::assertSame(0, $count);
        self::assertNull($ack->getResult());

        $error = $ack->getError();

        self::assertInstanceOf(\RuntimeException::class, $error);
        self::assertSame('handler error', $error->getMessage());
    }
}

class BatchHandlerTestHandler implements BatchHandlerInterface
{
    use BatchHandlerTrait;

    public function __invoke(BatchHandlerTestMessage $message, Acknowledger $ack = null): mixed
    {
        return $this->handle($message, $ack);
    }

    private function process(array $jobs): void
    {
        foreach ($jobs as [$message, $ack]) {
            try {
                if ($message->withError) {
                    throw new \RuntimeException('handler error');
                }

                $ack->ack('handler success');
            } catch (\Throwable $e) {
                $ack->nack($e);
            }
        }
    }

    private function shouldFlush(): bool
    {
        return true;
    }
}

class BatchHandlerTestMessage
{
    public bool $withError;

    public function __construct(bool $withError = false)
    {
        $this->withError = $withError;
    }
}
