<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sendora\Transport;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

final class SendoraTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();

        $transport = match ($dsn->getScheme()) {
            'sendora', 'sendora+api' => (new SendoraApiTransport($this->getUser($dsn), $this->client, $this->dispatcher, $this->logger))
                ->setHost($host)
                ->setPort($dsn->getPort()),
            'sendora+smtp' => (new SendoraSmtpTransport($this->getUser($dsn), $host, $dsn->getPort(), $this->dispatcher, $this->logger))
                ->setRequireTls($dsn->getBooleanOption('require_tls', true)),
            default => throw new UnsupportedSchemeException($dsn, 'sendora', $this->getSupportedSchemes()),
        };

        if (null !== $stream = $dsn->getOption('stream')) {
            $transport->setStream($stream);
        }

        return $transport;
    }

    protected function getSupportedSchemes(): array
    {
        return ['sendora', 'sendora+api', 'sendora+smtp'];
    }
}
