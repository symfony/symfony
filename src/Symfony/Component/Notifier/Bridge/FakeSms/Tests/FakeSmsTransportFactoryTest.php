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

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Notifier\Bridge\FakeSms\FakeSmsTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * @author James Hemery <james@yieldstudio.fr>
 */
final class FakeSmsTransportFactoryTest extends TransportFactoryTestCase
{
    /**
     * @return FakeSmsTransportFactory
     */
    public function createFactory(): TransportFactoryInterface
    {
        $serviceProvider = $this->createMock(ServiceProviderInterface::class);
        $serviceProvider->method('has')->willReturn(true);
        $serviceProvider->method('get')->willReturn($this->createMock(MailerInterface::class));

        return new FakeSmsTransportFactory($serviceProvider);
    }

    public function createProvider(): iterable
    {
        yield [
            'fakesms+email://mailer?to=recipient@email.net&from=sender@email.net',
            'fakesms+email://mailer?to=recipient@email.net&from=sender@email.net',
        ];
    }

    public function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: from' => ['fakesms+email://mailer?to=recipient@email.net'];
        yield 'missing option: to' => ['fakesms+email://mailer?from=sender@email.net'];
    }

    public function supportsProvider(): iterable
    {
        yield [true, 'fakesms+email://mailer?to=recipient@email.net&from=sender@email.net'];
        yield [false, 'somethingElse://mailer?to=recipient@email.net&from=sender@email.net'];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield 'missing from' => ['fakesms+email://mailer?to=recipient@email.net'];
        yield 'missing to' => ['fakesms+email://mailer?from=recipient@email.net'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://mailer?to=recipient@email.net&from=sender@email.net'];
    }
}
