<?php

namespace Symfony\Bundle\FrameworkBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller;
use Symfony\Component\HttpFoundation\Response;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * DefaultController.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class DefaultController extends Controller
{
    /**
     * Renders the Symfony2 welcome page.
     *
     * @return Response A Response instance
     */
    public function indexAction()
    {
        return $this['templating']->renderResponse('FrameworkBundle:Default:index');
    }
}
