<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Postmark\Transport;

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

        if ('postmark+api' === $scheme) {
            $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
            $port = $dsn->getPort();

            return (new PostmarkApiTransport($user, $this->client, $this->dispatcher, $this->logger))->setHost($host)->setPort($port);
        }

        if ('postmark+smtp' === $scheme || 'postmark+smtps' === $scheme || 'postmark' === $scheme) {
            return new PostmarkSmtpTransport($user, $this->dispatcher, $this->logger);
        }

        throw new UnsupportedSchemeException($dsn, 'postmark', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['postmark', 'postmark+api', 'postmark+smtp', 'postmark+smtps'];
    }
}
