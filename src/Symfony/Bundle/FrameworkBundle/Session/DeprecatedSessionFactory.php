<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Session;

use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Provides session and trigger deprecation.
 *
 * Used by service that should trigger deprecation when accessed by the user.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 *
 * @internal to be removed in 6.0
 */
class DeprecatedSessionFactory
{
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function getSession(): ?SessionInterface
    {
        trigger_deprecation('symfony/framework-bundle', '5.3', 'The "session" service and "SessionInterface" alias are deprecated, use "$requestStack->getSession()" instead.');

        try {
            return $this->requestStack->getSession();
        } catch (SessionNotFoundException $e) {
            return null;
        }
    }
}
