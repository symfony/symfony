<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Firebase;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Jeroen Spee <https://github.com/Jeroeny>
 */
final class FirebaseTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): FirebaseTransport|FirebaseJwtTransport
    {
        $scheme = $dsn->getScheme();
        if ('firebase-jwt' === $scheme) {
            return $this->createJwt($dsn);
        }

        if ('firebase' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'firebase', $this->getSupportedSchemes());
        }

        $token = sprintf('%s:%s', $this->getUser($dsn), $this->getPassword($dsn));
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new FirebaseTransport($token, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    public function createJwt(Dsn $dsn): FirebaseJwtTransport
    {
        $credentials = match ($this->getUser($dsn)) {
            'credentials_path' => file_get_contents($this->getPassword($dsn)),
            'credentials_content' => base64_decode($this->getPassword($dsn)),
        };

        return (new FirebaseJwtTransport(json_decode($credentials, true, 512, JSON_THROW_ON_ERROR), $this->client, $this->dispatcher));
    }

    protected function getSupportedSchemes(): array
    {
        return ['firebase', 'firebase-jwt'];
    }
}
