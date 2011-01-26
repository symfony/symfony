<?php

namespace Symfony\Component\Security\Http\Session;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\HttpFoundation\Request;

class SessionAuthenticationStrategy implements SessionAuthenticationStrategyInterface
{
    const NONE         = 'none';
    const MIGRATE      = 'migrate';
    const INVALIDATE   = 'invalidate';

    protected $strategy;

    public function __construct($strategy)
    {
        $this->strategy = $strategy;
    }

    public function onAuthentication(Request $request, TokenInterface $token)
    {
        switch ($this->strategy) {
            case self::NONE:
                return;

            case self::MIGRATE:
                $request->getSession()->migrate();
                return;

            case self::INVALIDATE:
                $request->getSession()->invalidate();
                return;

            default:
                throw new \RuntimeException(sprintf('Invalid session authentication strategy "%s"', $this->strategy));
        }
    }
}