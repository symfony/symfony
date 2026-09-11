<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\LineNotify\Tests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Symfony\Bridge\PhpUnit\ExpectUserDeprecationMessageTrait;
use Symfony\Component\Notifier\Bridge\LineNotify\LineNotifyTransportFactory;
use Symfony\Component\Notifier\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Notifier\Test\IncompleteDsnTestTrait;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Akira Kurozumi <info@a-zumi.net>
 */
#[Group('legacy')]
#[IgnoreDeprecations]
final class LineNotifyTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use ExpectUserDeprecationMessageTrait;
    use IncompleteDsnTestTrait;

    public function createFactory(): LineNotifyTransportFactory
    {
        return new LineNotifyTransportFactory();
    }

    public function testCreateIsDeprecated()
    {
        $this->expectUserDeprecationMessage('Since symfony/line-notify-notifier 8.2: The "symfony/line-notify-notifier" package is deprecated as LINE Notify was shut down, use "symfony/line-bot-notifier" instead.');

        $this->createFactory()->create(new Dsn('linenotify://token@default'));
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'linenotify://host'];
        yield [false, 'somethingElse://host'];
    }

    public static function createProvider(): iterable
    {
        yield [
            'linenotify://host.test',
            'linenotify://token@host.test',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing token' => ['linenotify://host.test'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://token@host'];
    }
}
