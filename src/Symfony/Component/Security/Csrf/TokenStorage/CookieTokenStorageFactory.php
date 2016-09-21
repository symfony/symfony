<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Csrf\TokenStorage;

use Symfony\Component\HttpFoundation\Request;

/**
 * Creates CSRF token storages based on the requests cookies.
 *
 * @author Oliver Hoff <oliver@hofff.com>
 */
class CookieTokenStorageFactory implements TokenStorageFactoryInterface
{
    /**
     * {@inheritdoc}
     *
     * @see \Symfony\Component\Security\Csrf\TokenStorage\TokenStorageFactoryInterface::createTokenStorage()
     */
    public function createTokenStorage(Request $request)
    {
        return new CookieTokenStorage($request->cookies, $request->isSecure());
    }
}
