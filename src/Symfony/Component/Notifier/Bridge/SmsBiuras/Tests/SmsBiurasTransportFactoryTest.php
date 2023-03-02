<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\SmsBiuras\Tests;

use Symfony\Component\Notifier\Bridge\SmsBiuras\SmsBiurasTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

final class SmsBiurasTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): SmsBiurasTransportFactory
    {
        return new SmsBiurasTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'smsbiuras://host.test?from=0611223344',
            'smsbiuras://uid:api_key@host.test?from=0611223344&test_mode=0',
        ];

        yield [
            'smsbiuras://host.test?from=0611223344&test_mode=1',
            'smsbiuras://uid:api_key@host.test?from=0611223344&test_mode=1',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'smsbiuras://uid:api_key@default?from=0611223344'];
        yield [false, 'somethingElse://uid:api_key@default?from=0611223344'];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: from' => ['smsbiuras://uid:api_key@default'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://uid:api_key@default?from=0611223344'];
        yield ['somethingElse://uid:api_key@default']; // missing "from" option
    }
}
