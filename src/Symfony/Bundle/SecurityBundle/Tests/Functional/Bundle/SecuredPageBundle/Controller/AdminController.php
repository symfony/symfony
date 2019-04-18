<?php

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\SecuredPageBundle\Controller;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\HttpFoundation\Response;

class AdminController implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function indexAction()
    {
        return new Response('admin');
    }
}
