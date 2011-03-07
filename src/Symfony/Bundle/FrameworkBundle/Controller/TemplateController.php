<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Controller;

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\HttpFoundation\Response;

/**
 * TemplateController.
 *
 * @author Fabien Potencier <fabien@symfony.com>
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
