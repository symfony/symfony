<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerAware;

/**
 * DefaultController.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class DefaultController extends ContainerAware
{
    /**
     * Renders the Symfony2 welcome page.
     *
     * @return Response A Response instance
     */
    public function indexAction()
    {
        return $this->container->get('templating')->renderResponse('FrameworkBundle:Default:index.html.twig');
    }
}
