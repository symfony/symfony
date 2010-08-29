<?php

namespace Symfony\Bundle\FrameworkBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpKernel\Exception\FlattenException;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;
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
class ExceptionController extends Controller
{
    /**
     * Converts an Exception to a Response.
     *
     * @param FlattenException     $exception A FlattenException instance
     * @param DebugLoggerInterface $logger    A DebugLoggerInterface instance
     * @param string               $format    The format to use for rendering (html, xml, ...)
     * @param Boolean              $embedded  Whether the rendered Response will be embedded or not
     *
     * @throws \InvalidArgumentException When the exception template does not exist
     */
    public function exceptionAction(FlattenException $exception, DebugLoggerInterface $logger, $format, $embedded = false)
    {
        $this['request']->setRequestFormat($format);

        $currentContent = '';
        while (false !== $content = ob_get_clean()) {
            $currentContent .= $content;
        }

        $response = $this->render(
            'FrameworkBundle:Exception:'.($this['kernel']->isDebug() ? 'exception' : 'error'),
            array(
                'exception'      => new SafeDecorator($exception),
                'logger'         => $logger,
                'currentContent' => $currentContent,
                'embedded'       => $embedded,
            )
        );
        $response->setStatusCode($exception->getStatusCode());

        return $response;
    }
}
