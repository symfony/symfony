<?php

namespace Symfony\Bundle\WebProfilerBundle\Controller;

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\HttpKernel\Exception\FlattenException;
use Symfony\Component\OutputEscaper\SafeDecorator;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * ExceptionController.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ExceptionController extends ContainerAware
{
    /**
     * Converts an Exception to a Response.
     *
     * @param \Exception $exception An Exception instance
     *
     * @throws \InvalidArgumentException When the exception template does not exist
     */
    public function showAction(FlattenException $exception, $format)
    {
        return $this->container->get('templating')->renderResponse(
            'FrameworkBundle:Exception:'.($this->container->get('kernel')->isDebug() ? 'exception' : 'error'),
            array(
                'exception'      => new SafeDecorator($exception),
                'logger'         => null,
                'currentContent' => '',
                'embedded'       => true,
            )
        );
    }
}
