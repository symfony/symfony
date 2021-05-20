<?php

namespace Symfony\Component\Security\Http\EntryPoint;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\HttpUtils;

class RouteAuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    /**
     * @var HttpUtils
     */
    private $httpUtils;

    /**
     * @var string
     */
    private $entryPointPath;

    public function __construct(HttpUtils $httpUtils, string $entryPointPath)
    {
        $this->httpUtils = $httpUtils;
        $this->entryPointPath = $entryPointPath;
    }

    public function start(Request $request, AuthenticationException $authException = null): RedirectResponse
    {
        return new RedirectResponse($this->httpUtils->generateUri($request, $this->entryPointPath));
    }
}
