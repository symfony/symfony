<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\FakeChat\Tests;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Notifier\Bridge\FakeChat\FakeChatTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class FakeChatTransportFactoryTest extends TransportFactoryTestCase
{
    /**
     * @return FakeChatTransportFactory
     */
    public function createFactory(): TransportFactoryInterface
    {
        $serviceProvider = $this->createMock(ServiceProviderInterface::class);
        $serviceProvider->method('has')->willReturn(true);
        $serviceProvider->method('get')->willReturn($this->createMock(MailerInterface::class));

        return new FakeChatTransportFactory($serviceProvider);
    }

    public function createProvider(): iterable
    {
        yield [
            'fakechat+email://mailer?to=recipient@email.net&from=sender@email.net',
            'fakechat+email://mailer?to=recipient@email.net&from=sender@email.net',
        ];
    }

    public function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: from' => ['fakechat+email://mailer?to=recipient@email.net'];
        yield 'missing option: to' => ['fakechat+email://mailer?from=sender@email.net'];
    }

    public function supportsProvider(): iterable
    {
        yield [true, 'fakechat+email://mailer?to=recipient@email.net&from=sender@email.net'];
        yield [false, 'somethingElse://mailer?to=recipient@email.net&from=sender@email.net'];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield 'missing from' => ['fakechat+email://mailer?to=recipient@email.net'];
        yield 'missing to' => ['fakechat+email://mailer?from=recipient@email.net'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://mailer?to=recipient@email.net&from=sender@email.net'];
    }
}
