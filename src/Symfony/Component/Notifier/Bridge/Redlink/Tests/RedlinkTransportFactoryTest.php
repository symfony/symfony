<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Redlink\Tests;

use Symfony\Component\Notifier\Bridge\Redlink\RedlinkTransportFactory;
use Symfony\Component\Notifier\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Notifier\Test\IncompleteDsnTestTrait;
use Symfony\Component\Notifier\Test\MissingRequiredOptionTestTrait;

final class RedlinkTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;
    use MissingRequiredOptionTestTrait;

    public function createFactory(): RedlinkTransportFactory
    {
        return new RedlinkTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'redlink://api.redlink.pl?from=TEST&version=v2.1',
            'redlink://aaaaa:bbbbbb@api.redlink.pl?from=TEST&version=v2.1',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'redlink://aaaaa:bbbbbb@default?from=TEST'];
        yield [false, 'somethingElse://aaaaa:bbbbbb@default?from=TEST'];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: from' => ['redlink://apiToken:appToken@default'];
        yield 'missing option: version' => ['redlink://apiToken:appToken@default?from=TEST'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://apiToken:appToken@default?from=FROM&version=FROM'];
        yield ['somethingElse://apiToken:appToken@default?from=FROM'];
        yield ['somethingElse://apiToken:appToken@default'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield ['redlink://aaaaa@default?from=TEST'];
        yield ['redlink://:bbbbbb@default?from=TEST'];
    }
}
