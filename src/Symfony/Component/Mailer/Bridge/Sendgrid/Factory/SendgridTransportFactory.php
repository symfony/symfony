<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sendgrid\Factory;

use Symfony\Component\Mailer\Bridge\Sendgrid;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * @author Konstantin Myakshin <molodchick@gmail.com>
 */
final class SendgridTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $key = $this->getUser($dsn);

        if ('api' === $dsn->getScheme()) {
            return new Sendgrid\Http\Api\SendgridTransport($key, $this->client, $this->dispatcher, $this->logger);
        }

        if ('smtp' === $dsn->getScheme()) {
            return new Sendgrid\Smtp\SendgridTransport($key, $this->dispatcher, $this->logger);
        }

        throw new UnsupportedSchemeException($dsn, ['api', 'smtp']);
    }

    public function supports(Dsn $dsn): bool
    {
        return 'sendgrid' === $dsn->getHost();
    }
}
