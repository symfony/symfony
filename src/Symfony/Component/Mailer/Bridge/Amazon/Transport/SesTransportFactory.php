<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Amazon\Transport;

use Symfony\Component\Mailer\Bridge\Amazon\Credential\InstanceCredentialProvider;
use Symfony\Component\Mailer\Bridge\Amazon\Credential\UsernamePasswordCredential;
use Symfony\Component\Mailer\Exception\IncompleteDsnException;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * @author Konstantin Myakshin <molodchick@gmail.com>
 */
final class SesTransportFactory extends AbstractTransportFactory
{
    private $credentialProvider;

    public function __construct(EventDispatcherInterface $dispatcher = null, HttpClientInterface $client = null, LoggerInterface $logger = null)
    {
        parent::__construct($dispatcher, $client, $logger);

        $this->credentialProvider = new InstanceCredentialProvider($client);
    }

    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        try {
            $credential = new UsernamePasswordCredential($this->getUser($dsn), $this->getPassword($dsn));
        } catch (IncompleteDsnException $e) {
            $role = $dsn->getOption('role');

            if (null === $role) {
                throw new IncompleteDsnException('User and password nor role is not set.');
            }

            $credential = $this->credentialProvider->getCredential($role);
        }
        $region = $dsn->getOption('region');

        if ('api' === $scheme) {
            return new SesApiTransport($credential, $region, $this->client, $this->dispatcher, $this->logger);
        }

        if ('http' === $scheme) {
            return new SesHttpTransport($credential, $region, $this->client, $this->dispatcher, $this->logger);
        }

        if ('smtp' === $scheme || 'smtps' === $scheme) {
            return new SesSmtpTransport($credential, $region, $this->dispatcher, $this->logger);
        }

        throw new UnsupportedSchemeException($dsn, ['api', 'http', 'smtp', 'smtps']);
    }

    public function supports(Dsn $dsn): bool
    {
        return 'ses' === $dsn->getHost();
    }
}
