<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Logout;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LogoutEvent;

trigger_deprecation('symfony/security-http', '5.1', 'The "%s" interface is deprecated, create a listener for the "%s" event instead.', LogoutSuccessHandlerInterface::class, LogoutEvent::class);

/**
 * LogoutSuccesshandlerInterface.
 *
 * In contrast to the LogoutHandlerInterface, this interface can return a response
 * which is then used instead of the default behavior.
 *
 * If you want to only perform some logout related clean-up task, use the
 * LogoutHandlerInterface instead.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @deprecated since Symfony 5.1
 */
interface LogoutSuccessHandlerInterface
{
    /**
     * Creates a Response object to send upon a successful logout.
     *
     * @return Response never null
     */
    public function onLogoutSuccess(Request $request);
}
