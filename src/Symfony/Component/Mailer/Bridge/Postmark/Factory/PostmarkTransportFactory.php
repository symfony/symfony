<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Postmark\Factory;

use Symfony\Component\Mailer\Bridge\Postmark;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * @author Konstantin Myakshin <molodchick@gmail.com>
 */
final class PostmarkTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        $user = $this->getUser($dsn);

        if ('api' === $scheme) {
            return new Postmark\Http\Api\PostmarkTransport($user, $this->client, $this->dispatcher, $this->logger);
        }

        if ('smtp' === $scheme) {
            return new Postmark\Smtp\PostmarkTransport($user, $this->dispatcher, $this->logger);
        }

        throw new UnsupportedSchemeException($dsn);
    }

    public function supports(Dsn $dsn): bool
    {
        return 'postmark' === $dsn->getHost();
    }
}
