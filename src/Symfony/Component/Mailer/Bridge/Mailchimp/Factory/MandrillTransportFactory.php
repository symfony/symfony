<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailchimp\Factory;

use Symfony\Component\Mailer\Bridge\Mailchimp;
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

        if ('api' === $scheme) {
            return new Mailchimp\Http\Api\MandrillTransport($user, $this->client, $this->dispatcher, $this->logger);
        }

        if ('http' === $scheme) {
            return new Mailchimp\Http\MandrillTransport($user, $this->client, $this->dispatcher, $this->logger);
        }

        if ('smtp' === $scheme) {
            $password = $this->getPassword($dsn);

            return new Mailchimp\Smtp\MandrillTransport($user, $password, $this->dispatcher, $this->logger);
        }

        throw new UnsupportedSchemeException($dsn, ['api', 'http', 'smtp']);
    }

    public function supports(Dsn $dsn): bool
    {
        return 'mandrill' === $dsn->getHost();
    }
}
