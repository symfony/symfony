<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\PagerDuty\Tests;

use Symfony\Component\Notifier\Bridge\PagerDuty\PagerDutyTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

final class PagerDutyTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): PagerDutyTransportFactory
    {
        return new PagerDutyTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'pagerduty://subdomain.pagerduty.com',
            'pagerduty://token@subdomain.pagerduty.com',
            'pagerduty://token@subdomain.eu.pagerduty.com',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'pagerduty://host'];
        yield [false, 'somethingElse://host'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing token' => ['pagerduty://@host'];
        yield 'wrong host' => ['pagerduty://token@host.com'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://token@host'];
    }
}
