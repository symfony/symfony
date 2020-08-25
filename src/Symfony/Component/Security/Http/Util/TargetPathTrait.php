<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Util;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Trait to get (and set) the URL the user last visited before being forced to authenticate.
 */
trait TargetPathTrait
{
    /**
     * Sets the target path the user should be redirected to after authentication.
     *
     * Usually, you do not need to set this directly.
     */
    private function saveTargetPath(SessionInterface $session, string $firewallName, string $uri)
    {
        $session->set('_security.'.$firewallName.'.target_path', $uri);
    }

    /**
     * Returns the URL (if any) the user visited that forced them to login.
     */
    private function getTargetPath(SessionInterface $session, string $firewallName): ?string
    {
        return $session->get('_security.'.$firewallName.'.target_path');
    }

    /**
     * Removes the target path from the session.
     */
    private function removeTargetPath(SessionInterface $session, string $firewallName)
    {
        $session->remove('_security.'.$firewallName.'.target_path');
    }
}
