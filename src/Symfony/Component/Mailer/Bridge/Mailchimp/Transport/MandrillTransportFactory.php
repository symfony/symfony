<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailchimp\Transport;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * @author Konstantin Myakshin <molodchick@gmail.com>
 */
final class MandrillTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        $user = $this->getUser($dsn);
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        if ('mandrill+api' === $scheme) {
            return (new MandrillApiTransport($user, $this->client, $this->dispatcher, $this->logger))->setHost($host)->setPort($port);
        }

        if ('mandrill+https' === $scheme || 'mandrill' === $scheme) {
            return (new MandrillHttpTransport($user, $this->client, $this->dispatcher, $this->logger))->setHost($host)->setPort($port);
        }

        if ('mandrill+smtp' === $scheme || 'mandrill+smtps' === $scheme) {
            $password = $this->getPassword($dsn);

            return new MandrillSmtpTransport($user, $password, $this->dispatcher, $this->logger);
        }

        throw new UnsupportedSchemeException($dsn, 'mandrill', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['mandrill', 'mandrill+api', 'mandrill+https', 'mandrill+smtp', 'mandrill+smtps'];
    }
}
