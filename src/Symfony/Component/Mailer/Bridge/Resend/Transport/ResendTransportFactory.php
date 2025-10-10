<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Resend\Transport;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class ResendTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        return match ($dsn->getScheme()) {
            'resend', 'resend+smtp' => new ResendSmtpTransport($this->getPassword($dsn), $this->dispatcher, $this->logger),
            'resend+api' => (new ResendApiTransport($this->getUser($dsn), $this->client, $this->dispatcher, $this->logger))
                ->setHost('default' === $dsn->getHost() ? null : $dsn->getHost())
                ->setPort($dsn->getPort()),
            default => throw new UnsupportedSchemeException($dsn, 'resend', $this->getSupportedSchemes()),
        };
    }

    protected function getSupportedSchemes(): array
    {
        return ['resend', 'resend+smtp', 'resend+api'];
    }
}
