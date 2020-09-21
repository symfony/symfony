<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Bridge\Crowdin;

use Symfony\Component\Translation\Exception\UnsupportedSchemeException;
use Symfony\Component\Translation\Remote\AbstractRemoteFactory;
use Symfony\Component\Translation\Remote\Dsn;
use Symfony\Component\Translation\Remote\RemoteInterface;

final class CrowdinRemoteFactory extends AbstractRemoteFactory
{
    /**
     * @return CrowdinRemote
     */
    public function create(Dsn $dsn): RemoteInterface
    {
        if ('crowdin' === $dsn->getScheme()) {
            return (new CrowdinRemote($this->getUser($dsn), $this->client, $this->loader, $this->logger, $this->defaultLocale))
                ->setHost('default' === $dsn->getHost() ? null : $dsn->getHost())
                ->setPort($dsn->getPort())
            ;
        }

        throw new UnsupportedSchemeException($dsn, 'crowdin', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['crowdin'];
    }
}
