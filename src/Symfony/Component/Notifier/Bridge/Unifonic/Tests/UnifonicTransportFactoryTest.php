<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Unifonic\Tests;

use Symfony\Component\Notifier\Bridge\Unifonic\UnifonicTransportFactory;
use Symfony\Component\Notifier\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Notifier\Test\IncompleteDsnTestTrait;

final class UnifonicTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;

    public function createFactory(): UnifonicTransportFactory
    {
        return new UnifonicTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'unifonic://host.test?from=Sender',
            'unifonic://s3cr3t@host.test?from=Sender',
        ];
        yield [
            'unifonic://host.test',
            'unifonic://s3cr3t@host.test',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'unifonic://host.test?from=Sender'];
        yield [true, 'unifonic://default?from=Sender'];
        yield [false, 'somethingElse://host.test?from=Sender'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://host.test?from=Sender'];
        yield ['somethingElse://s3cr3t@host.test?from=Sender'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield ['unifonic://host.test', 'Invalid "unifonic://host.test" notifier DSN: User is not set.'];
    }
}
