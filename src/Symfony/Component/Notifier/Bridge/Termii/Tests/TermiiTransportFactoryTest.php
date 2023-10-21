<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Termii\Tests;

use Symfony\Component\Notifier\Bridge\Termii\TermiiTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

final class TermiiTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): TermiiTransportFactory
    {
        return new TermiiTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield ['termii://host.test?from=0611223344&channel=generic', 'termii://apiKey@host.test?from=0611223344&channel=generic'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing auth ID' => ['termii://@default?from=FROM'];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: from' => ['termii://apiKey@default?channel=generic'];
        yield 'missing option: channel' => ['termii://apiKey@default?from=0611223344'];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'termii://apiKey@default?from=0611223344&channel=generic'];
        yield [false, 'somethingElse://apiKey@default?from=0611223344&channel=generic'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://apiKey@default?from=0611223344&channel=generic'];
        yield ['somethingElse://apiKey@default']; // missing "from" option
    }
}
