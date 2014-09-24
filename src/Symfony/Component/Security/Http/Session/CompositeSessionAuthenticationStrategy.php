<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Session;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * A session authentication strategy that accepts multiple
 * SessionAuthenticationStrategyInterface implementations to delegate to. Each
 * SessionAuthenticationStrategyInterface is invoked in turn. The invocations are
 * short circuited if any exception, (i.e. SessionAuthenticationException) is
 * thrown.
 *
 * @author Antonio J. García Lagar <aj@garcialagar.es>
 */
class CompositeSessionAuthenticationStrategy implements SessionAuthenticationStrategyInterface
{
    /**
     * @var array
     */
    private $delegateStrategies = array();

    public function __construct(array $delegateStrategies)
    {
        foreach ($delegateStrategies as $strategy) {
            $this->addDelegateStrategy($strategy);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function onAuthentication(Request $request, TokenInterface $token)
    {
        foreach ($this->delegateStrategies as $strategy) {
            /* @var $strategy SessionAuthenticationStrategyInterface */
            $strategy->onAuthentication($request, $token);
        }
    }

    private function addDelegateStrategy(SessionAuthenticationStrategyInterface $strategy)
    {
        $this->delegateStrategies[] = $strategy;
    }
}
