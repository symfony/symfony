<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Brevo\Tests;

use Symfony\Component\Notifier\Bridge\Brevo\BrevoTransportFactory;
use Symfony\Component\Notifier\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Notifier\Test\IncompleteDsnTestTrait;
use Symfony\Component\Notifier\Test\MissingRequiredOptionTestTrait;

final class BrevoTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;
    use MissingRequiredOptionTestTrait;

    public function createFactory(): BrevoTransportFactory
    {
        return new BrevoTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'brevo://host.test?sender=0611223344',
            'brevo://apiKey@host.test?sender=0611223344',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'brevo://apiKey@default?sender=0611223344'];
        yield [false, 'somethingElse://apiKey@default?sender=0611223344'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing api_key' => ['brevo://default?sender=0611223344'];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: sender' => ['brevo://apiKey@host.test'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://apiKey@default?sender=0611223344'];
        yield ['somethingElse://apiKey@host']; // missing "sender" option
    }
}
