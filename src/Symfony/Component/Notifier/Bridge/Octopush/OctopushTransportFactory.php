<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Octopush;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Aurélien Martin <pro@aurelienmartin.com>
 */
final class OctopushTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): OctopushTransport
    {
        $scheme = $dsn->getScheme();

        if ('octopush' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'octopush', $this->getSupportedSchemes());
        }

        $userLogin = urlencode($this->getUser($dsn));
        $apiKey = $this->getPassword($dsn);
        $from = $dsn->getRequiredOption('from');
        $type = $dsn->getRequiredOption('type');

        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new OctopushTransport($userLogin, $apiKey, $from, $type, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['octopush'];
    }
}
