<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\MailKite\Transport;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * @author Gabe (MailKite) <bucabay@gmail.com>
 */
final class MailKiteTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if ('mailkite+api' === $scheme) {
            $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();

            return (new MailKiteApiTransport($this->getUser($dsn), $this->client, $this->dispatcher, $this->logger))
                ->setHost($host)
                ->setPort($dsn->getPort());
        }

        if ('mailkite' === $scheme || 'mailkite+smtp' === $scheme || 'mailkite+smtps' === $scheme) {
            return new MailKiteSmtpTransport($this->getUser($dsn), 'mailkite+smtps' === $scheme, $this->dispatcher, $this->logger);
        }

        throw new UnsupportedSchemeException($dsn, 'mailkite', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['mailkite', 'mailkite+api', 'mailkite+smtp', 'mailkite+smtps'];
    }
}
