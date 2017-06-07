<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\RememberMe;

use Symfony\Component\Security\Http\RememberMe\RememberMeServicesInterface;
use Symfony\Component\Security\Http\RememberMe\RememberMeServicesResolverInterface;

class FakeRememberMeServicesResolver implements RememberMeServicesResolverInterface
{
    private $rememberMeServices;

    public function __construct()
    {
        $this->rememberMeServices = array();
    }

    public function addRememberMeServices($providerKey, RememberMeServicesInterface $rememberMeServices)
    {
        $this->rememberMeServices[$providerKey] = $rememberMeServices;
    }

    /**
     * @param $providerKey
     * @return null|RememberMeServicesInterface
     */
    public function resolve($providerKey)
    {
        return array_key_exists($providerKey, $this->rememberMeServices)
            ? $this->rememberMeServices[$providerKey]
            : null;
    }
}
