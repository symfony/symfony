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
use Symfony\Component\Notifier\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;

/**
 * @author Mickael Perraud <mikaelkael.fr@gmail.com>
 */
final class NtfyTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    public function createFactory(): TransportFactoryInterface
    {
        return new NtfyTransportFactory();
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
