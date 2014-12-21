<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Security\Http\RememberMe;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Http\RememberMe\RememberMeServicesInterface;
use Symfony\Component\Security\Http\RememberMe\RememberMeServicesResolverInterface;

class RememberMeServicesResolver implements RememberMeServicesResolverInterface
{
    /**
     * @var \Symfony\Component\DependencyInjection\ContainerInterface
     */
    private $serviceContainer;

    /**
     * @param ContainerInterface $serviceContainer
     */
    public function __construct(ContainerInterface $serviceContainer)
    {
        $this->serviceContainer = $serviceContainer;
    }

    /**
     * @param $providerKey
     * @return null|RememberMeServicesInterface
     */
    public function resolve($providerKey)
    {
        $rememberMeServices = null;
        if ($this->serviceContainer->has('security.authentication.rememberme.services.persistent.'.$providerKey)) {
            $rememberMeServices = $this->serviceContainer->get('security.authentication.rememberme.services.persistent.'.$providerKey);
        } elseif ($this->serviceContainer->has('security.authentication.rememberme.services.simplehash.'.$providerKey)) {
            $rememberMeServices = $this->serviceContainer->get('security.authentication.rememberme.services.simplehash.'.$providerKey);
        }

        if ($rememberMeServices instanceof RememberMeServicesInterface) {
            return $rememberMeServices;
        }

        return;
    }
}
