<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\Security\Http\Firewall\ExceptionListener;
use Symfony\Component\Security\Http\Firewall\LogoutListener;

/**
 * FirewallMap allows configuration of different firewalls for specific parts
 * of the website.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class FirewallMap implements FirewallMapInterface
{
    /**
     * @var list<array{RequestMatcherInterface, list<callable>, ExceptionListener|null, list<callable>, LogoutListener|null}>
     */
    private $map = [];

    /**
     * @param list<callable> $listeners
     */
    public function add(RequestMatcherInterface $requestMatcher = null, array $listeners = [], ExceptionListener $exceptionListener = null, LogoutListener $logoutListener = null)
    {
        $allListeners = $listeners;

        if (null !== $logoutListener) {
            trigger_deprecation('symfony/security', '5.4', 'Passing the LogoutListener as forth argument is deprecated, add it to $listeners instead.', __METHOD__);

            // Ensure LogoutListener is contained in all listeners list
            if (!\in_array($logoutListener, $allListeners)) {
                $allListeners[] = $logoutListener;
            }
        } else {
            // Take LogoutListeners from all listeners list
            foreach ($listeners as $listener) {
                if ($listener instanceof LogoutListener) {
                    $logoutListener = $listener;
                    break;
                }
            }
        }

        $this->map[] = [$requestMatcher, $allListeners, $exceptionListener, $listeners, $logoutListener];
    }

    /**
     * {@inheritdoc}
     */
    public function getListeners(Request $request)
    {
        if (2 > \func_num_args() || func_get_arg(1)) {
            trigger_deprecation('symfony/security', '5.4', 'The %s() method is deprecated, use getFirewallListeners() or "getExceptionListener()" instead.', __METHOD__);
        }

        foreach ($this->map as $elements) {
            if (null === $elements[0] || $elements[0]->matches($request)) {
                return [$elements[3], $elements[2], $elements[4]];
            }
        }

        return [[], null, null];
    }

    public function getFirewallListeners(Request $request): iterable
    {
        foreach ($this->map as $elements) {
            if (null === $elements[0] || $elements[0]->matches($request)) {
                return $elements[1];
            }
        }

        return [];
    }

    public function getExceptionListener(Request $request): ?ExceptionListener
    {
        foreach ($this->map as $elements) {
            if (null === $elements[0] || $elements[0]->matches($request)) {
                return $elements[2];
            }
        }

        return null;
    }
}
