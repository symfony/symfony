<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Isendpro;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

final class IsendproTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): IsendproTransport
    {
        if ('isendpro' !== $dsn->getScheme()) {
            throw new UnsupportedSchemeException($dsn, 'isendpro', $this->getSupportedSchemes());
        }

        $keyid = $this->getUser($dsn);
        $from = $dsn->getOption('from', null);
        $noStop = filter_var($dsn->getOption('no_stop', false), \FILTER_VALIDATE_BOOLEAN);
        $sandbox = filter_var($dsn->getOption('sandbox', false), \FILTER_VALIDATE_BOOLEAN);
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new IsendproTransport($keyid, $from, $noStop, $sandbox, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['isendpro'];
    }
}
