<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\LinkedIn;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Smaïne Milianni <smaine.milianni@gmail.com>
 */
final class LinkedInTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): LinkedInTransport
    {
        $scheme = $dsn->getScheme();

        if ('linkedin' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'linkedin', $this->getSupportedSchemes());
        }

        $authToken = $this->getUser($dsn);
        $accountId = $this->getPassword($dsn);
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new LinkedInTransport($authToken, $accountId, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['linkedin'];
    }
}
