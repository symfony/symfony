<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Prelude\Tests;

use Symfony\Component\Notifier\Bridge\Prelude\PreludeTransportFactory;
use Symfony\Component\Notifier\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Notifier\Test\IncompleteDsnTestTrait;

final class PreludeTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;

    public function createFactory(): PreludeTransportFactory
    {
        return new PreludeTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'prelude://host.test?sender=0611223344',
            'prelude://apiKey@host.test?sender=0611223344',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'prelude://apiKey@default?sender=0611223344'];
        yield [false, 'somethingElse://apiKey@default?sender=0611223344'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing api_key' => ['prelude://default?sender=0611223344'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://apiKey@default?sender=0611223344'];
    }
}
