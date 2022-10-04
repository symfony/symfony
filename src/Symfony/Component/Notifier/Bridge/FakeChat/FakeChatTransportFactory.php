<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\FakeChat;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 * @author Antoine Makdessi <amakdessi@me.com>
 */
final class FakeChatTransportFactory extends AbstractTransportFactory
{
    private MailerInterface $mailer;
    private LoggerInterface $logger;

    public function __construct(MailerInterface $mailer, LoggerInterface $logger)
    {
        parent::__construct();

        $this->mailer = $mailer;
        $this->logger = $logger;
    }

    public function create(Dsn $dsn): FakeChatEmailTransport|FakeChatLoggerTransport
    {
        $scheme = $dsn->getScheme();

        if ('fakechat+email' === $scheme) {
            $mailerTransport = $dsn->getHost();
            $to = $dsn->getRequiredOption('to');
            $from = $dsn->getRequiredOption('from');

            return (new FakeChatEmailTransport($this->mailer, $to, $from))->setHost($mailerTransport);
        }

        if ('fakechat+logger' === $scheme) {
            return new FakeChatLoggerTransport($this->logger);
        }

        throw new UnsupportedSchemeException($dsn, 'fakechat', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['fakechat+email', 'fakechat+logger'];
    }
}
