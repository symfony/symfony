<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Http\Logout;

use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\Security\Http\HttpUtils;

/**
 * Default logout success handler will redirect users to a configured path.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 * @author Alexander <iam.asm89@gmail.com>
 */
class DefaultLogoutSuccessHandler implements LogoutSuccessHandlerInterface
{
    protected $httpUtils;
    protected $targetUrl;

    public function __construct(HttpUtils $httpUtils, string $targetUrl = '/')
    {
        $this->httpUtils = $httpUtils;
        $this->targetUrl = $targetUrl;
    }

    /**
     * {@inheritdoc}
     */
    public function onLogoutSuccess(Request $request)
    {
        return $this->httpUtils->createRedirectResponse($request, $this->targetUrl);
    }
}
