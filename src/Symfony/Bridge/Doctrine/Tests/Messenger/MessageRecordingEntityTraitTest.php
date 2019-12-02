<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Messenger;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Tests\Fixtures\MessageRecordingEntity;

class MessageRecordingEntityTraitTest extends TestCase
{
    public function testDispatch(): void
    {
        $entity = new MessageRecordingEntity();
        $message = new \stdClass();

        $entity->doRecordMessage($message);

        $entity->dispatchMessages(static function (array $messages) use ($message): void {
            static::assertSame([$message], $messages);
        });
    }

    public function testMessagesClearedAfterDispatch(): void
    {
        $entity = new MessageRecordingEntity();
        $entity->doRecordMessage(new \stdClass());

        $entity->dispatchMessages(static function (): void {
        });

        $entity->dispatchMessages(static function (array $messages): void {
            static::assertCount(0, $messages);
        });
    }

    public function testMessagesClearedOnDispatchFailure(): void
    {
        $entity = new MessageRecordingEntity();
        $entity->doRecordMessage(new \stdClass());

        try {
            $entity->dispatchMessages(static function (): void {
                throw new \Exception();
            });
        } catch (\Exception $exception) {
        }

        $entity->dispatchMessages(static function (array $messages): void {
            static::assertCount(0, $messages);
        });
    }
}
