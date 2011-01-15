<?php

namespace Symfony\Bundle\FrameworkBundle\Controller;

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\HttpKernel\Exception\FlattenException;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;
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
 * ExceptionController.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ExceptionController extends ContainerAware
{
    /**
     * Converts an Exception to a Response.
     *
     * @param FlattenException     $exception A FlattenException instance
     * @param DebugLoggerInterface $logger    A DebugLoggerInterface instance
     * @param string               $format    The format to use for rendering (html, xml, ...)
     *
     * @throws \InvalidArgumentException When the exception template does not exist
     */
    public function showAction(FlattenException $exception, DebugLoggerInterface $logger = null, $format = 'html')
    {
        $this->container->get('request')->setRequestFormat($format);

        $currentContent = '';
        while (ob_get_level()) {
            $currentContent .= ob_get_clean();
        }

        $code = $this->getStatusCode($exception);

        $name = $this->container->get('kernel')->isDebug() ? 'exception' : 'error';
        if ($this->container->get('kernel')->isDebug() && 'html' == $format) {
            $name = 'exception_full';
        }
        $template = 'FrameworkBundle:Exception:'.$name.'.twig.'.$format;

        $templating = $this->container->get('templating');
        if (!$templating->exists($template)) {
            $this->container->get('request')->setRequestFormat('html');
            $template = 'FrameworkBundle:Exception:'.$name.'.twig.html';
        }

        $response = $templating->renderResponse(
            $template,
            array(
                'status_code'    => $code,
                'status_text'    => Response::$statusTexts[$code],
                'exception'      => $exception,
                'logger'         => $logger,
                'currentContent' => $currentContent,
            )
        );

        $response->setStatusCode($code);

        return $response;
    }

    protected function getStatusCode(FlattenException $exception)
    {
        switch ($exception->getClass()) {
            case 'Symfony\Component\Security\Exception\AccessDeniedException':
                return 403;
            case 'Symfony\Component\HttpKernel\Exception\NotFoundHttpException':
                return 404;
            default:
                return 500;
        }
    }
}
