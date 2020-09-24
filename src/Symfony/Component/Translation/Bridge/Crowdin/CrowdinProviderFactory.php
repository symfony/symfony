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
use Symfony\Component\Translation\Provider\AbstractProviderFactory;
use Symfony\Component\Translation\Provider\Dsn;
use Symfony\Component\Translation\Provider\ProviderInterface;

final class CrowdinProviderFactory extends AbstractProviderFactory
{
    /**
     * @return CrowdinProvider
     */
    public function create(Dsn $dsn): ProviderInterface
    {
        if ('crowdin' === $dsn->getScheme()) {
            return (new CrowdinProvider($this->getUser($dsn), $this->client, $this->loader, $this->logger, $this->defaultLocale))
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
