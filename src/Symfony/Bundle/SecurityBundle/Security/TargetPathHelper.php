<?php
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Security;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

final class TargetPathHelper
{
    use TargetPathTrait;

    private $session;
    private $firewallMap;
    private $requestStack;

    /**
     * TargetPathHelper constructor.
     *
     * @param SessionInterface $session
     * @param FirewallMap      $firewallMap
     */
    public function __construct(SessionInterface $session, FirewallMap $firewallMap, RequestStack $requestStack)
    {
        $this->session = $session;
        $this->firewallMap = $firewallMap;
        $this->requestStack = $requestStack;
    }

    /**
     * Sets the target path the user should be redirected to after the authentication.
     *
     * @param string $uri The URI to set as the target path
     */
    public function savePath(string $uri)
    {
        $this->saveTargetPath($this->session, $this->getProviderKey(), $uri);
    }

    /**
     * Returns the URL (if any) the user visited that forced them to login.
     */
    public function getPath(): string
    {
        return $this->getTargetPath($this->session, $this->getProviderKey());
    }

    private function getProviderKey(): string
    {
        $firewallConfig = $this->firewallMap->getFirewallConfig($this->requestStack->getMasterRequest());

        if (null === $firewallConfig) {
            throw new \LogicException("Couldn't find firewall config for master request");
        }

        return $firewallConfig->getName();
    }
}
