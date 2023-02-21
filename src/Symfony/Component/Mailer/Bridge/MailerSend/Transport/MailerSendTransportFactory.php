<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\MailerSend\Transport;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * @author Yann LUCAS
 */
final class MailerSendTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        return match ($dsn->getScheme()) {
            'mailersend+api' => (new MailerSendApiTransport($this->getUser($dsn), $this->client, $this->dispatcher, $this->logger))
                ->setHost('default' === $dsn->getHost() ? null : $dsn->getHost())
                ->setPort($dsn->getPort()),

            'mailersend', 'mailersend+smtp' => new MailerSendSmtpTransport($this->getUser($dsn), $this->getPassword($dsn), $this->dispatcher, $this->logger),

            default => throw new UnsupportedSchemeException($dsn, 'mailersend', $this->getSupportedSchemes())
        };
    }

    protected function getSupportedSchemes(): array
    {
        return ['mailersend', 'mailersend+smtp', 'mailersend+api'];
    }
}
