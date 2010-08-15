<?php

namespace Symfony\Bundle\FrameworkBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller;
use Symfony\Bundle\FrameworkBundle\Debug\ExceptionManager;

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
class ExceptionController extends Controller
{
    /**
     * Converts an Exception to a Response.
     *
     * @param ExceptionManager $manager An ExceptionManager instance
     *
     * @throws \InvalidArgumentException When the exception template does not exist
     */
    public function exceptionAction(ExceptionManager $manager)
    {
        $this['request']->setRequestFormat($manager->getFormat());

        $response = $this->render(
            'FrameworkBundle:Exception:'.($this['kernel']->isDebug() ? 'exception' : 'error'),
            array('manager' => $manager)
        );
        $response->setStatusCode($manager->getStatusCode());

        return $response;
    }
}
