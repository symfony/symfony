<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\FakeSms\Tests;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Notifier\Bridge\FakeSms\FakeSmsTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;

final class FakeSmsTransportFactoryTest extends TransportFactoryTestCase
{
    /**
     * @return FakeSmsTransportFactory
     */
    public function createFactory(): TransportFactoryInterface
    {
        return new FakeSmsTransportFactory(self::createMock(MailerInterface::class), self::createMock(LoggerInterface::class));
    }

    public function createProvider(): iterable
    {
        yield [
            'fakesms+email://default?to=recipient@email.net&from=sender@email.net',
            'fakesms+email://default?to=recipient@email.net&from=sender@email.net',
        ];

        yield [
            'fakesms+email://mailchimp?to=recipient@email.net&from=sender@email.net',
            'fakesms+email://mailchimp?to=recipient@email.net&from=sender@email.net',
        ];

        yield [
            'fakesms+logger://default',
            'fakesms+logger://default',
        ];
    }

    public function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: from' => ['fakesms+email://default?to=recipient@email.net'];
        yield 'missing option: to' => ['fakesms+email://default?from=sender@email.net'];
    }

    public function supportsProvider(): iterable
    {
        yield [true, 'fakesms+email://default?to=recipient@email.net&from=sender@email.net'];
        yield [false, 'somethingElse://default?to=recipient@email.net&from=sender@email.net'];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield 'missing from' => ['fakesms+email://default?to=recipient@email.net'];
        yield 'missing to' => ['fakesms+email://default?from=recipient@email.net'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://default?to=recipient@email.net&from=sender@email.net'];
    }
}
