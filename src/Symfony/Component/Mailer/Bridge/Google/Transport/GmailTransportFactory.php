<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Google\Transport;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\Bridge\Google\TokenManager;
use Symfony\Component\Mailer\Exception\IncompleteDsnException;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * @author Konstantin Myakshin <molodchick@gmail.com>
 */
final class GmailTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        if (!\in_array($dsn->getScheme(), $this->getSupportedSchemes(), true)) {
            throw new UnsupportedSchemeException($dsn, 'gmail', $this->getSupportedSchemes());
        }

        if ('gmail+api' !== $dsn->getScheme()) {
            return new GmailSmtpTransport($this->getUser($dsn), $this->getPassword($dsn), $this->dispatcher, $this->logger);
        }

        $userEmail = $dsn->getOption('user');
        if (null === $userEmail) {
            throw new IncompleteDsnException('Transport "gmail+api" requires the "user" option to specify the email address to send from.');
        }

        $serviceAccountEmail = $this->getUser($dsn);
        $privateKey = $this->getPassword($dsn);

        // The private key must be base64-encoded in the DSN to avoid parsing issues
        $decodedPrivateKey = base64_decode($privateKey, true);
        if (false === $decodedPrivateKey) {
            throw new IncompleteDsnException('Transport "gmail+api" requires a valid base64-encoded private key.');
        }

        $client = $this->client;
        if (null === $client) {
            if (!class_exists(HttpClient::class)) {
                throw new \LogicException(\sprintf('You cannot use "%s" as the HttpClient component is not installed. Try running "composer require symfony/http-client".', GmailApiTransport::class));
            }

            $client = HttpClient::create();
        }

        $tokenManager = new TokenManager($serviceAccountEmail, $decodedPrivateKey, $userEmail, $client);

        return new GmailApiTransport($tokenManager, $userEmail, $client, $this->dispatcher, $this->logger);
    }

    protected function getSupportedSchemes(): array
    {
        return ['gmail', 'gmail+smtp', 'gmail+smtps', 'gmail+api'];
    }
}
