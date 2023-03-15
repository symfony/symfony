<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Bandwidth\Tests;

use Symfony\Component\Notifier\Bridge\Bandwidth\BandwidthTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

final class BandwidthTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): BandwidthTransportFactory
    {
        return new BandwidthTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield ['bandwidth://host.test?from=0611223344&account_id=account_id&application_id=application_id&priority=priority', 'bandwidth://username:password@host.test?from=0611223344&account_id=account_id&application_id=application_id&priority=priority'];
        yield ['bandwidth://host.test?from=0611223344&account_id=account_id&application_id=application_id', 'bandwidth://username:password@host.test?from=0611223344&account_id=account_id&application_id=application_id'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing password' => ['bandwidth://username@default?account_id=account_id&application_id=application_id&priority=priority'];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: from' => ['bandwidth://username:password@default?account_id=account_id&application_id=application_id&priority=priority'];
        yield 'missing option: account_id' => ['bandwidth://username:password@default?from=0611223344&application_id=application_id&priority=priority'];
        yield 'missing option: application_id' => ['bandwidth://username:password@default?from=0611223344&account_id=account_id&priority=priority'];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'bandwidth://username:password@default?from=0611223344&account_id=account_id&application_id=application_id&priority=priority'];
        yield [false, 'somethingElse://username:password@default?from=0611223344&account_id=account_id&application_id=application_id&priority=priority'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://username:password@default?from=0611223344&account_id=account_id&application_id=application_id&priority=priority'];
        yield ['somethingElse://username:password@default?account_id=account_id&application_id=application_id&priority=priority']; // missing "from" option
    }
}
