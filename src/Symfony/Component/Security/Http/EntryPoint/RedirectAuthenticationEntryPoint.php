<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\EntryPoint;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\HttpUtils;

/**
 * Redirects the user to a path or a route to start the authentication.
 */
final class RedirectAuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    /**
     * @param string $path A path, an absolute URL or a route name
     */
    public function __construct(
        private readonly HttpUtils $httpUtils,
        private readonly string $path,
    ) {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): RedirectResponse
    {
        return new RedirectResponse($this->httpUtils->generateUri($request, $this->path));
    }
}
