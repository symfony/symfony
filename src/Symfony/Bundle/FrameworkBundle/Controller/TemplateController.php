<?php

namespace Symfony\Bundle\FrameworkBundle\Controller;

use Symfony\Component\DependencyInjection\ContainerAware;
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
 * TemplateController.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class TemplateController extends ContainerAware
{
    /**
     * Renders a template.
     *
     * @param string $template The template name
     *
     * @return Response A Response instance
     */
    public function templateAction($template)
    {
        return $this->container->get('templating')->renderResponse($template);
    }
}
