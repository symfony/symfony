<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\SecurityBundle\Tests\Functional\Bundle\FormLoginBundle\Security;

use Symphony\Component\HttpFoundation\RedirectResponse;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\Routing\Generator\UrlGeneratorInterface;
use Symphony\Component\Routing\RouterInterface;
use Symphony\Component\Security\Core\Exception\AuthenticationException;
use Symphony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

class LocalizedFormFailureHandler implements AuthenticationFailureHandlerInterface
{
    private $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        return new RedirectResponse($this->router->generate('localized_login_path', array(), UrlGeneratorInterface::ABSOLUTE_URL));
    }
}
