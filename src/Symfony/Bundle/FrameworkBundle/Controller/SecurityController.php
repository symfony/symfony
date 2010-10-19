<?php

namespace Symfony\Bundle\FrameworkBundle\Controller;

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\Security\SecurityContext;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * SecurityController.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class SecurityController extends ContainerAware
{
    /**
     * Displays the login form.
     *
     * @return Response A Response instance
     */
    public function loginAction()
    {
        $request = $this->container->get('request');
        if ($request->attributes->has(SecurityContext::AUTHENTICATION_ERROR)) {
            $error = $request->attributes->get(SecurityContext::AUTHENTICATION_ERROR);
        } else {
            $error = $request->getSession()->get(SecurityContext::AUTHENTICATION_ERROR);
        }

        return $this->container->get('templating')->renderResponse('FrameworkBundle:Security:login.php', array(
            'last_username' => $request->getSession()->get(SecurityContext::LAST_USERNAME),
            'error'         => $error,
        ));
    }
}
