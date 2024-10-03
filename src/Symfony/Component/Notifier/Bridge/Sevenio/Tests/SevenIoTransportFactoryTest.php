<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Sevenio\Tests;

use Symfony\Component\Notifier\Bridge\Sevenio\SevenIoTransportFactory;
use Symfony\Component\Notifier\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Notifier\Test\IncompleteDsnTestTrait;

final class SevenIoTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;

    public function createFactory(): SevenIoTransportFactory
    {
        return new SevenIoTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'sevenio://host.test',
            'sevenio://apiKey@host.test',
        ];

        yield [
            'sevenio://host.test?from=TEST',
            'sevenio://apiKey@host.test?from=TEST',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing api key' => ['sevenio://host?from=TEST'];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'sevenio://apiKey@default?from=TEST'];
        yield [false, 'somethingElse://apiKey@default?from=TEST'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://apiKey@default?from=FROM'];
    }
}
