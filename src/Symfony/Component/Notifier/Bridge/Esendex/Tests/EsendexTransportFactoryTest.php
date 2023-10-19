<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Esendex\Tests;

use Symfony\Component\Notifier\Bridge\Esendex\EsendexTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

final class EsendexTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): EsendexTransportFactory
    {
        return new EsendexTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'esendex://host.test?accountreference=ACCOUNTREFERENCE&from=FROM',
            'esendex://email:password@host.test?accountreference=ACCOUNTREFERENCE&from=FROM',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'esendex://email:password@host?accountreference=ACCOUNTREFERENCE&from=FROM'];
        yield [false, 'somethingElse://email:password@default'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing credentials' => ['esendex://host?accountreference=ACCOUNTREFERENCE&from=FROM'];
        yield 'missing email' => ['esendex://:password@host?accountreference=ACCOUNTREFERENCE&from=FROM'];
        yield 'missing password' => ['esendex://email:@host?accountreference=ACCOUNTREFERENCE&from=FROM'];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: from' => ['esendex://email:password@host?accountreference=ACCOUNTREFERENCE'];
        yield 'missing option: accountreference' => ['esendex://email:password@host?from=FROM'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://email:password@default?accountreference=ACCOUNTREFERENCE&from=FROM'];
        yield ['somethingElse://email:password@host?accountreference=ACCOUNTREFERENCE']; // missing "from" option
    }
}
