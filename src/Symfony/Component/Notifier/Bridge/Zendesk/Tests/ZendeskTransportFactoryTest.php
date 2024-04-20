<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Zendesk\Tests;

use Symfony\Component\Notifier\Bridge\Zendesk\ZendeskTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

final class ZendeskTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): ZendeskTransportFactory
    {
        return new ZendeskTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'zendesk://subdomain.zendesk.com',
            'zendesk://email:token@subdomain.zendesk.com',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'zendesk://host'];
        yield [false, 'somethingElse://host'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing email or token' => ['zendesk://testOneOfEmailOrToken@host'];
        yield 'wrong host' => ['zendesk://testEmail:Token@host.com'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://email:token@host'];
    }
}
