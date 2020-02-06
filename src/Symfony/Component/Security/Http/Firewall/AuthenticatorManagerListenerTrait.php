<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Firewall;

use Symfony\Component\HttpFoundation\Request;

/**
 * @author Ryan Weaver <ryan@knpuniversity.com>
 * @author Amaury Leroux de Lens <amaury@lerouxdelens.com>
 *
 * @internal
 */
trait AuthenticatorManagerListenerTrait
{
    protected function getSupportingAuthenticators(Request $request): array
    {
        $authenticators = [];
        foreach ($this->authenticators as $key => $authenticator) {
            if (null !== $this->logger) {
                $this->logger->debug('Checking support on authenticator.', ['firewall_key' => $this->providerKey, 'authenticator' => \get_class($authenticator)]);
            }

            if ($authenticator->supports($request)) {
                $authenticators[$key] = $authenticator;
            } elseif (null !== $this->logger) {
                $this->logger->debug('Authenticator does not support the request.', ['firewall_key' => $this->providerKey, 'authenticator' => \get_class($authenticator)]);
            }
        }

        return $authenticators;
    }
}
