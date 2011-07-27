<?php

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\FormLoginBundle\Controller;

use Symfony\Component\Security\Core\SecurityContext;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerAware;

class LocalizedController extends ContainerAware
{
    public function loginAction()
    {
        // get the login error if there is one
        if ($this->container->get('request')->attributes->has(SecurityContext::AUTHENTICATION_ERROR)) {
            $error = $this->container->get('request')->attributes->get(SecurityContext::AUTHENTICATION_ERROR);
        } else {
            $error = $this->container->get('request')->getSession()->get(SecurityContext::AUTHENTICATION_ERROR);
        }

        return $this->container->get('templating')->renderResponse('FormLoginBundle:Localized:login.html.twig', array(
            // last username entered by the user
            'last_username' => $this->container->get('request')->getSession()->get(SecurityContext::LAST_USERNAME),
            'error'         => $error,
        ));
    }

    public function loginCheckAction()
    {
        throw new \RuntimeException('loginCheckAction() should never be called.');
    }

    public function logoutAction()
    {
        throw new \RuntimeException('logoutAction() should never be called.');
    }

    public function secureAction()
    {
        throw new \RuntimeException('secureAction() should never be called.');
    }

    public function profileAction()
    {
        return new Response('Profile');
    }

    public function homepageAction()
    {
        return new Response('Homepage');
    }
}
