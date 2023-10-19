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
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author James Hemery <james@yieldstudio.fr>
 * @author Oskar Stark <oskarstark@googlemail.com>
 * @author Antoine Makdessi <amakdessi@me.com>
 */
final class FakeSmsTransportFactory extends AbstractTransportFactory
{
    private ?MailerInterface $mailer;
    private ?LoggerInterface $logger;

    public function __construct(MailerInterface $mailer = null, LoggerInterface $logger = null)
    {
        parent::__construct();

        $this->mailer = $mailer;
        $this->logger = $logger;
    }

    public function create(Dsn $dsn): FakeSmsEmailTransport|FakeSmsLoggerTransport
    {
        $scheme = $dsn->getScheme();

        if ('fakesms+email' === $scheme) {
            if (null === $this->mailer) {
                $this->throwMissingDependencyException($scheme, MailerInterface::class, 'symfony/mailer');
            }

            $mailerTransport = $dsn->getHost();
            $to = $dsn->getRequiredOption('to');
            $from = $dsn->getRequiredOption('from');

            return (new FakeSmsEmailTransport($this->mailer, $to, $from))->setHost($mailerTransport);
        }

        if ('fakesms+logger' === $scheme) {
            if (null === $this->logger) {
                $this->throwMissingDependencyException($scheme, LoggerInterface::class, 'psr/log');
            }

            return new FakeSmsLoggerTransport($this->logger);
        }

        throw new UnsupportedSchemeException($dsn, 'fakesms', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['fakesms+email', 'fakesms+logger'];
    }

    private function throwMissingDependencyException(string $scheme, string $missingDependency, string $suggestedPackage): void
    {
        throw new LogicException(sprintf('Cannot create a transport for scheme "%s" without providing an implementation of "%s". Try running "composer require "%s"".', $scheme, $missingDependency, $suggestedPackage));
    }
}
