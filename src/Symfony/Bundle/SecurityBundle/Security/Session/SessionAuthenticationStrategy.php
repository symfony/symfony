<?php

namespace Symfony\Bundle\SecurityBundle\Security\Session;

use Symfony\Component\Security\Authentication\Token\TokenInterface;
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
                break;

            case self::INVALIDATE:
                $request->getSession()->invalidate();

            default:
                throw new \RuntimeException(sprintf('Invalid session authentication strategy "%s"', $this->strategy));
        }
    }
}