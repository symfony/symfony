<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authentication\Token;

/**
 * Marker interface for tokens that should not be persisted to the session.
 *
 * Implement this interface on tokens created by stateless authenticators
 * (e.g. API token authenticators) to prevent session persistence and
 * related side effects such as session cookies and profiler debug cookies.
 *
 * @author Jonathan Bonjour <hi@gnutix.dev>
 */
interface StatelessTokenInterface extends TokenInterface
{
}
