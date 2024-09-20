<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\ContactEveryone\Tests;

use Symfony\Component\Notifier\Bridge\ContactEveryone\ContactEveryoneTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

final class ContactEveryoneTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): ContactEveryoneTransportFactory
    {
        return new ContactEveryoneTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'contact-everyone://host.test',
            'contact-everyone://token@host.test',
        ];

        yield [
            'contact-everyone://host.test?diffusionname=Symfony',
            'contact-everyone://token@host.test?diffusionname=Symfony',
        ];

        yield [
            'contact-everyone://host.test?category=Symfony',
            'contact-everyone://token@host.test?category=Symfony',
        ];

        yield [
            'contact-everyone://host.test?diffusionname=Symfony&category=Symfony',
            'contact-everyone://token@host.test?diffusionname=Symfony&category=Symfony',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'contact-everyone://token@default'];
        yield [false, 'somethingElse://token@default'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing token' => ['contact-everyone://default'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://token@default'];
    }
}
