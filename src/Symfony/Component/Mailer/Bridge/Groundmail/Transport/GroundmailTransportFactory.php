<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Groundmail\Transport;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * @author Shivansh Mehendiratta <shivansh@backslope.studio>
 */
final class GroundmailTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if ('groundmail' === $scheme || 'groundmail+api' === $scheme) {
            return (new GroundmailApiTransport($this->getUser($dsn), $this->client, $this->dispatcher, $this->logger))
                ->setHost('default' === $dsn->getHost() ? null : $dsn->getHost())
                ->setPort($dsn->getPort());
        }

        if ('groundmail+smtp' === $scheme) {
            return (new GroundmailSmtpTransport($this->getUser($dsn), $this->getPassword($dsn), $dsn->getPort(), $this->dispatcher, $this->logger))
                ->setRequireTls($dsn->getBooleanOption('require_tls', true));
        }

        throw new UnsupportedSchemeException($dsn, 'groundmail', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['groundmail', 'groundmail+api', 'groundmail+smtp'];
    }
}
