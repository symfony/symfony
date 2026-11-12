<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Ntfy\Tests;

use Symfony\Component\Notifier\Bridge\Ntfy\NtfyTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;

/**
 * @author Mickael Perraud <mikaelkael.fr@gmail.com>
 */
final class NtfyTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): TransportFactoryInterface
    {
        return new NtfyTransportFactory();
    }

    public function testSecureHttpOption()
    {
        $cases = [
            '' => true,
            'secureHttp=1' => true,
            'secureHttp=on' => true,
            'secureHttp=0' => false,
            'secureHttp=' => false,
            'secureHttp=off' => false,
            'secureHttp=Off' => false,
            'secureHttp=FALSE' => false,
        ];

        foreach ($cases as $query => $secureHttp) {
            $transport = $this->createFactory()->create(new Dsn('ntfy://user:password@default/test?'.$query));
            $this->assertSame($secureHttp, (new \ReflectionProperty($transport, 'secureHttp'))->getValue($transport), $query);
        }
    }

    public static function createProvider(): iterable
    {
        yield [
            'ntfy://ntfy.sh/test',
            'ntfy://user:password@default/test',
        ];
        yield [
            'ntfy://ntfy.sh/test',
            'ntfy://:password@default/test',
        ];
        yield [
            'ntfy://ntfy.sh:8888/test',
            'ntfy://user:password@default:8888/test?secureHttp=off',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'ntfy://default/test'];
        yield [false, 'somethingElse://default/test'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://default/test'];
    }
}
