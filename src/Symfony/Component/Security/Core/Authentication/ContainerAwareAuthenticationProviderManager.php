<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authentication;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Uses the Container to lazily instantiate the providers.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
class ContainerAwareAuthenticationProviderManager extends AbstractAuthenticationProviderManager
{
    private $providerServiceIds;

    private $container;

    private $eraseCredentials;

    public function __construct(array $providerServiceIds, ContainerInterface $container, $eraseCredentials = true)
    {
        $this->providerServiceIds = $providerServiceIds;
        $this->container = $container;
        $this->eraseCredentials = $eraseCredentials;
    }

    protected function getProviders()
    {
        foreach ($this->providerServiceIds as $serviceId) {
            yield $this->container->get($serviceId);
        }
    }

    protected function shouldEraseCredentials()
    {
        return $this->eraseCredentials;
    }
}
