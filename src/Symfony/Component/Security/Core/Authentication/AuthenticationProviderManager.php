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

use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class AuthenticationProviderManager extends AbstractAuthenticationProviderManager
{
    private $providers;
    private $eraseCredentials;

    /**
     * Constructor.
     *
     * @param AuthenticationProviderInterface[] $providers        An array of AuthenticationProviderInterface instances
     * @param bool                              $eraseCredentials Whether to erase credentials after authentication or not
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(array $providers, $eraseCredentials = true)
    {
        $this->providers = $providers;
        $this->eraseCredentials = (bool) $eraseCredentials;
    }

    protected function getProviders()
    {
        return $this->providers;
    }

    protected function shouldEraseCredentials()
    {
        return $this->eraseCredentials;
    }
}
