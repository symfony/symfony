<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Bridge\Loco;

use Symfony\Component\Translation\Exception\UnsupportedSchemeException;
use Symfony\Component\Translation\Remote\AbstractRemoteFactory;
use Symfony\Component\Translation\Remote\Dsn;
use Symfony\Component\Translation\Remote\RemoteInterface;

final class LocoRemoteFactory extends AbstractRemoteFactory
{
    /**
     * @return LocoRemote
     */
    public function create(Dsn $dsn): RemoteInterface
    {
        $scheme = $dsn->getScheme();
        $apiKey = $this->getUser($dsn);
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        if ('loco' === $scheme) {
            return (new LocoRemote($apiKey, $this->client, $this->logger, $this->loader, $this->defaultLocale))
                ->setHost($host)
                ->setPort($port)
            ;
        }

        throw new UnsupportedSchemeException($dsn, 'loco', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['loco'];
    }
}
