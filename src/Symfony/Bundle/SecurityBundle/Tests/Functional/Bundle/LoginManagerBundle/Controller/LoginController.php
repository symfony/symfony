<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\LoginManagerBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerAware;

class LoginController extends ContainerAware
{
    public function loginAction()
    {
        $user = $this->container->get('security.user.provider.concrete.in_memory')->loadUserByUsername('norzechowicz');
        $this->container->get('security.login_manager')->loginUser('secured_area', $user);

        return new Response();
    }

    public function loginCheckAction()
    {
        return new Response('', 400);
    }
}
