<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Telegram\Tests;

use Symfony\Component\Notifier\Bridge\Telegram\TelegramTransportFactory;
use Symfony\Component\Notifier\Tests\TransportFactoryTestCase;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;

final class TelegramTransportFactoryTest extends TransportFactoryTestCase
{
    /**
     * @return TelegramTransportFactory
     */
    public function createFactory(): TransportFactoryInterface
    {
        return new TelegramTransportFactory();
    }

    public function createProvider(): iterable
    {
        yield [
            'telegram://host.test?channel=testChannel',
            'telegram://user:password@host.test?channel=testChannel',
        ];
    }

    public function supportsProvider(): iterable
    {
        yield [true, 'telegram://host?channel=testChannel'];
        yield [false, 'somethingElse://host?channel=testChannel'];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield 'missing password' => ['telegram://token@host.test?channel=testChannel'];
        yield 'missing token' => ['telegram://host.test?channel=testChannel'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://user:pwd@host'];
    }
}
