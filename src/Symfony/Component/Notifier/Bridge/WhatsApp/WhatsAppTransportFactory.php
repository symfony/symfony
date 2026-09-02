<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\WhatsApp;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Piero Recchia <piero.recchia@gmail.com>
 */
final class WhatsAppTransportFactory extends AbstractTransportFactory
{
    private const DEFAULT_API_VERSION = 'v26.0';

    public function create(Dsn $dsn): WhatsAppTransport
    {
        $scheme = $dsn->getScheme();

        if ('whatsapp' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'whatsapp', $this->getSupportedSchemes());
        }

        $accessToken = $this->getUser($dsn);
        $phoneNumberId = $dsn->getRequiredOption('phone_number_id');
        $apiVersion = $dsn->getOption('api_version', self::DEFAULT_API_VERSION);
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new WhatsAppTransport($accessToken, $phoneNumberId, $apiVersion, $this->client, $this->dispatcher))->setHost($host)->setPort($port)->setSsl($this->getSsl($dsn));
    }

    protected function getSupportedSchemes(): array
    {
        return ['whatsapp'];
    }
}
