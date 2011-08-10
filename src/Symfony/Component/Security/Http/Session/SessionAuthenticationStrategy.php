<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Component\Security\Http\Session;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * The default session strategy implementation.
 *
 * Supports the following strategies:
 * NONE: the session is not changed
 * MIGRATE: the session id is updated, attributes are kept
 * INVALIDATE: the session id is updated, attributes are lost
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class SessionAuthenticationStrategy implements SessionAuthenticationStrategyInterface
{
    const NONE         = 'none';
    const MIGRATE      = 'migrate';
    const INVALIDATE   = 'invalidate';

    private $strategy;

    public function __construct($strategy)
    {
        $this->strategy = $strategy;
    }

    /**
     * {@inheritDoc}
     */
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
