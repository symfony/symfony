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
     *
     * @param SessionInterface $session
     * @param string           $providerKey The name of your firewall
     * @param string           $uri         The URI to set as the target path
     */
    private function saveTargetPath(SessionInterface $session, string $providerKey, string $uri): void
    {
        $session->set('_security.'.$providerKey.'.target_path', $uri);
    }

    /**
     * Returns the URL (if any) the user visited that forced them to login.
     *
     * @param SessionInterface $session
     * @param string           $providerKey The name of your firewall
     *
     * @return string
     */
    private function getTargetPath(SessionInterface $session, string $providerKey): string
    {
        return $session->get('_security.'.$providerKey.'.target_path');
    }

    /**
     * Removes the target path from the session.
     *
     * @param SessionInterface $session
     * @param string           $providerKey The name of your firewall
     */
    private function removeTargetPath(SessionInterface $session, string $providerKey): void
    {
        $session->remove('_security.'.$providerKey.'.target_path');
    }
}
