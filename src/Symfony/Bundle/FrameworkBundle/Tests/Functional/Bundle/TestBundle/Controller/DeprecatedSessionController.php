<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class DeprecatedSessionController extends AbstractController
{
    public function triggerAction()
    {
        $this->get('session');

        return new Response('done');
    }
}
