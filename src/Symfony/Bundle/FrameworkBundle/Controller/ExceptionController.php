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
use Symfony\Component\HttpKernel\Exception\FlattenException;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * ExceptionController.
 *
 * @author Fabien Potencier <fabien@symfony.com>
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

        // the count variable avoids an infinite loop on
        // some Windows configurations where ob_get_level()
        // never reaches 0
        $count = 100;
        $currentContent = '';
        while (ob_get_level() && --$count) {
            $currentContent .= ob_get_clean();
        }

        $name = $this->container->get('kernel')->isDebug() ? 'exception' : 'error';
        if ($this->container->get('kernel')->isDebug() && 'html' == $format) {
            $name = 'exception_full';
        }
        $template = 'FrameworkBundle:Exception:'.$name.'.'.$format.'.twig';

        $templating = $this->container->get('templating');
        if (!$templating->exists($template)) {
            $this->container->get('request')->setRequestFormat('html');
            $template = 'FrameworkBundle:Exception:'.$name.'.html.twig';
        }

        $code = $exception->getStatusCode();
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
        $response->headers->replace($exception->getHeaders());

        return $response;
    }
}
