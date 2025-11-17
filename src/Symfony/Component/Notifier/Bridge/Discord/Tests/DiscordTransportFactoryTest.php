<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Discord\Tests;

use Symfony\Component\Notifier\Bridge\Discord\DiscordTransportFactory;
use Symfony\Component\Notifier\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Notifier\Test\IncompleteDsnTestTrait;
use Symfony\Component\Notifier\Test\MissingRequiredOptionTestTrait;

final class DiscordTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;
    use MissingRequiredOptionTestTrait;

    public function createFactory(): DiscordTransportFactory
    {
        return new DiscordTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'discord://host.test?webhook_id=testWebhookId',
            'discord://token@host.test?webhook_id=testWebhookId',
        ];
        yield [
            'discord+bot://host.test',
            'discord+bot://token@host.test',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'discord://host?webhook_id=testWebhookId'];
        yield [true, 'discord+bot://token@host'];
        yield [false, 'somethingElse://host?webhook_id=testWebhookId'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing token' => ['discord://host.test?webhook_id=testWebhookId'];
        yield 'missing bot token' => ['discord+bot://host.test', 'Invalid "discord+bot://host.test" notifier DSN: User is not set.'];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: webhook_id' => ['discord://token@host'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://token@host?webhook_id=testWebhookId'];
        yield ['somethingElse://token@host']; // missing "webhook_id" option
    }
}
