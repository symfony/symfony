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
use Symfony\Component\Translation\Provider\AbstractProviderFactory;
use Symfony\Component\Translation\Provider\Dsn;
use Symfony\Component\Translation\Provider\ProviderInterface;

final class LocoProviderFactory extends AbstractProviderFactory
{
    /**
     * @return LocoProvider
     */
    public function create(Dsn $dsn): ProviderInterface
    {
        if ('loco' === $dsn->getScheme()) {
            return (new LocoProvider($this->getUser($dsn), $this->client, $this->loader, $this->logger, $this->defaultLocale))
                ->setHost('default' === $dsn->getHost() ? null : $dsn->getHost())
                ->setPort($dsn->getPort())
            ;
        }

        throw new UnsupportedSchemeException($dsn, 'loco', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['loco'];
    }
}
