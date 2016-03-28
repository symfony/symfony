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
 * Creates CSRF token storages.
 *
 * @author Oliver Hoff <oliver@hofff.com>
 */
interface TokenStorageFactoryInterface
{
    /**
     * Creates a new token storage for the given request.
     *
     * @param Request $request
     * @return TokenStorageInterface
     */
    public function createTokenStorage(Request $request);
}
