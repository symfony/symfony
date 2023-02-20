<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Sendinblue\Tests;

use Symfony\Component\Notifier\Bridge\Sendinblue\SendinblueTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

final class SendinblueTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): SendinblueTransportFactory
    {
        return new SendinblueTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'sendinblue://host.test?sender=0611223344',
            'sendinblue://apiKey@host.test?sender=0611223344',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'sendinblue://apiKey@default?sender=0611223344'];
        yield [false, 'somethingElse://apiKey@default?sender=0611223344'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing api_key' => ['sendinblue://default?sender=0611223344'];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: sender' => ['sendinblue://apiKey@host.test'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://apiKey@default?sender=0611223344'];
        yield ['somethingElse://apiKey@host']; // missing "sender" option
    }
}
