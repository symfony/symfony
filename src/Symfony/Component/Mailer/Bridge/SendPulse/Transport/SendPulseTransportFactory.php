<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\SendPulse\Transport;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * @author Oleh Boiko <developer@mackrais.com>
 */
final class SendPulseTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        return match ($dsn->getScheme()) {
            'sendpulse+api' => (new SendPulseApiTransport($this->getUser($dsn), $this->getPassword($dsn), $this->client, $this->dispatcher, $this->logger))
                ->setHost('default' === $dsn->getHost() ? null : $dsn->getHost())
                ->setPort($dsn->getPort()),

            'sendpulse', 'sendpulse+smtp' => new SendPulseSmtpTransport($this->getUser($dsn), $this->getPassword($dsn), $this->dispatcher, $this->logger),

            default => throw new UnsupportedSchemeException($dsn, 'sendpulse', $this->getSupportedSchemes()),
        };
    }

    protected function getSupportedSchemes(): array
    {
        return ['sendpulse', 'sendpulse+smtp', 'sendpulse+api'];
    }
}
