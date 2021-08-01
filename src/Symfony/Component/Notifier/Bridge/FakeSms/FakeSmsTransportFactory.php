<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\FakeSms;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * @author James Hemery <james@yieldstudio.fr>
 * @author Oskar Stark <oskarstark@googlemail.com>
 * @author Antoine Makdessi <amakdessi@me.com>
 */
final class FakeSmsTransportFactory extends AbstractTransportFactory
{
    protected $mailer;
    protected $logger;

    public function __construct(MailerInterface $mailer, LoggerInterface $logger)
    {
        parent::__construct();

        $this->mailer = $mailer;
        $this->logger = $logger;
    }

    /**
     * @return FakeSmsEmailTransport|FakeSmsLoggerTransport
     */
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if ('fakesms+email' === $scheme) {
            $mailerTransport = $dsn->getHost();
            $to = $dsn->getRequiredOption('to');
            $from = $dsn->getRequiredOption('from');

            return (new FakeSmsEmailTransport($this->mailer, $to, $from))->setHost($mailerTransport);
        }

        if ('fakesms+logger' === $scheme) {
            return new FakeSmsLoggerTransport($this->logger);
        }

        throw new UnsupportedSchemeException($dsn, 'fakesms', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['fakesms+email', 'fakesms+logger'];
    }
}
