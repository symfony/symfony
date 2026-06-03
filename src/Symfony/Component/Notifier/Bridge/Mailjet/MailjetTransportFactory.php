<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Mailjet;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Jérôme Nadaud <jerome@nadaud.io>
 */
final class MailjetTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): MailjetTransport
    {
        $scheme = $dsn->getScheme();

        if ('mailjet' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'mailjet', $this->getSupportedSchemes());
        }

        $authToken = $this->getPassword($dsn);
        $from = $this->getUser($dsn);
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new MailjetTransport($authToken, $from, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['mailjet'];
    }
}
