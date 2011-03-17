<?php

namespace Symfony\Component\Security\Http;

use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Firewall\ExceptionListener;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * FirewallMap allows configuration of different firewalls for specific parts
 * of the website.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class FirewallMap implements FirewallMapInterface
{
    private $map = array();

    public function add(RequestMatcherInterface $requestMatcher = null, array $listeners = array(), ExceptionListener $exceptionListener = null)
    {
        $this->map[] = array($requestMatcher, $listeners, $exceptionListener);
    }

    public function getListeners(Request $request)
    {
        foreach ($this->map as $elements) {
            if (null === $elements[0] || $elements[0]->matches($request)) {
                return array($elements[1], $elements[2]);
            }
        }

        return array(array(), null);
    }
}
