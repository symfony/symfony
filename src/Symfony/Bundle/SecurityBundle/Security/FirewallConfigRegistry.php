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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Stores firewall config objects.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class FirewallConfigRegistry
{
    private $firewallConfigs;
    private $requestStack;
    private $requestMatchers;

    /**
     * @param FirewallConfig[]  $firewallConfigs
     * @param RequestMatcher[]  $requestMatchers Indexed by firewall name
     * @param RequestStack|null $requestStack    To get the current firewall config
     */
    public function __construct(array $firewallConfigs = array(), array $requestMatchers = array(), RequestStack $requestStack = null)
    {
        $this->firewallConfigs = $firewallConfigs;
        $this->requestStack = $requestStack;
        $this->requestMatchers = $requestMatchers;
    }

    /**
     * @param string $name The firewall name
     *
     * @return FirewallConfig|null
     */
    public function get($name)
    {
        foreach ($this->firewallConfigs as $config) {
            if ($config->getName() === $name) {
                return $config;
            }
        }
    }

    /**
     * @return FirewallConfig|null
     */
    public function current()
    {
        if (!$this->requestStack) {
            throw new \LogicException('Unable to get current firewall config without a RequestStack.');
        }

        return $this->fromRequest($this->requestStack->getCurrentRequest());
    }

    /**
     * @param Request $request
     *
     * @return FirewallConfig|null
     */
    public function fromRequest(Request $request)
    {
        foreach ($this->firewallConfigs as $config) {
            $requestMatcher = $this->getRequestMatcher($config);
            if (null === $requestMatcher || $requestMatcher->matches($request)) {
                return $config;
            }
        }
    }

    /**
     * @return FirewallConfig[]
     */
    public function all()
    {
        return $this->firewallConfigs;
    }

    /**
     * @return FirewallConfig[]
     */
    public function inContext($context)
    {
        return array_filter($this->firewallConfigs, function (FirewallConfig $config) use ($context) {
            return $context === $config->getContext();
        });
    }

    /**
     * @param FirewallConfig $config
     *
     * @return RequestMatcher|null
     */
    private function getRequestMatcher(FirewallConfig $config)
    {
        if (empty($config->getRequestMatcher())) {
            return;
        }

        $firewallName = $config->getName();

        if (!isset($this->requestMatchers[$firewallName])) {
            throw new \LogicException(sprintf('Request matcher not found for "%s" firewall.', $firewallName));
        }

        return $this->requestMatchers[$firewallName];
    }
}
