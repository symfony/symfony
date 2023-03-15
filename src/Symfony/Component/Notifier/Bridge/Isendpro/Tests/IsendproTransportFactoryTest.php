<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Isendpro\Tests;

use Symfony\Component\Notifier\Bridge\Isendpro\IsendproTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

final class IsendproTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): IsendproTransportFactory
    {
        return new IsendproTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'isendpro://host.test?no_stop=0&sandbox=0',
            'isendpro://account_key_id@host.test',
        ];

        yield [
            'isendpro://host.test?from=FROM&no_stop=0&sandbox=0',
            'isendpro://account_key_id@host.test?from=FROM',
        ];

        yield [
            'isendpro://host.test?from=FROM&no_stop=0&sandbox=0',
            'isendpro://account_key_id@host.test?from=FROM&no_stop=0&sandbox=0',
        ];

        yield [
            'isendpro://host.test?from=FROM&no_stop=0&sandbox=0',
            'isendpro://account_key_id@host.test?from=FROM&no_stop=false&sandbox=0',
        ];

        yield [
            'isendpro://host.test?from=FROM&no_stop=1&sandbox=0',
            'isendpro://account_key_id@host.test?from=FROM&no_stop=1&sandbox=0',
        ];

        yield [
            'isendpro://host.test?from=FROM&no_stop=1&sandbox=1',
            'isendpro://account_key_id@host.test?from=FROM&no_stop=1&sandbox=true',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'isendpro://account_key_id@host?from=FROM'];
        yield [false, 'somethingElse://account_key_id@default'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing credentials' => ['isendpro://host?from=FROM'];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: account_key_id' => ['isendpro://default'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://account_key_id@default'];
    }

    /**
     * @dataProvider missingRequiredOptionProvider
     */
    public function testMissingRequiredOptionException(string $dsn, string $message = null)
    {
        $this->markTestIncomplete('The only required option is account key id, matched by incompleteDsnProvider');
    }
}
