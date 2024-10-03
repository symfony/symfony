<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Vonage\Tests;

use Symfony\Component\Notifier\Bridge\Vonage\VonageTransportFactory;
use Symfony\Component\Notifier\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Notifier\Test\IncompleteDsnTestTrait;
use Symfony\Component\Notifier\Test\MissingRequiredOptionTestTrait;

final class VonageTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;
    use MissingRequiredOptionTestTrait;

    public function createFactory(): VonageTransportFactory
    {
        return new VonageTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'vonage://host.test?from=0611223344',
            'vonage://apiKey:apiSecret@host.test?from=0611223344',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'vonage://apiKey:apiSecret@default?from=0611223344'];
        yield [false, 'somethingElse://apiKey:apiSecret@default?from=0611223344'];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: from' => ['vonage://apiKey:apiSecret@default'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://apiKey:apiSecret@default?from=0611223344'];
        yield ['somethingElse://apiKey:apiSecret@default']; // missing "from" option
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield ['vonage://:apiSecret@default?from=0611223344'];
        yield ['vonage://apiKey:@default?from=0611223344'];
    }
}
