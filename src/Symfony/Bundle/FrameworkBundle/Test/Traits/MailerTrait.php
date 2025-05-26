<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Test\Traits;

use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\Event\MessageEvents;
use Symfony\Component\Mime\RawMessage;

trait MailerTrait
{
    /**
     * @return MessageEvent[]
     */
    public static function getMailerEvents(?string $transport = null): array
    {
        return self::getMailerMessageEvents()->getEvents($transport);
    }

    public static function getMailerEvent(int $index = 0, ?string $transport = null): ?MessageEvent
    {
        return self::getMailerEvents($transport)[$index] ?? null;
    }

    /**
     * @return RawMessage[]
     */
    public static function getMailerMessages(?string $transport = null): array
    {
        return self::getMailerMessageEvents()->getMessages($transport);
    }

    public static function getMailerMessage(int $index = 0, ?string $transport = null): ?RawMessage
    {
        return self::getMailerMessages($transport)[$index] ?? null;
    }

    public static function getMailerMessageEvents(): MessageEvents
    {
        $container = static::getContainer();
        if ($container->has('mailer.message_logger_listener')) {
            return $container->get('mailer.message_logger_listener')->getEvents();
        }

        static::fail('A client must have Mailer enabled to make email assertions. Did you forget to require symfony/mailer?');
    }
}
