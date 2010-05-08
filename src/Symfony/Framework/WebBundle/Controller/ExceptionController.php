<?php

namespace Symfony\Framework\WebBundle\Controller;

use Symfony\Framework\WebBundle\Controller;
use Symfony\Framework\WebBundle\Debug\ExceptionFormatter;
use Symfony\Components\HttpKernel\Request;
use Symfony\Components\HttpKernel\Response;
use Symfony\Components\HttpKernel\Exception\HttpException;

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
 * @package    Symfony
 * @subpackage Framework_WebBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ExceptionController extends Controller
{
    /**
     * @throws \InvalidArgumentException When the exception template does not exist
     */
    public function exceptionAction(\Exception $exception, Request $originalRequest, array $logs)
    {
        $template = $this->container->getParameter('kernel.debug') ? 'exception' : 'error';

        $request = $this->getRequest();
        $format = $format = $originalRequest->getRequestFormat();

        // when using CLI, we force the format to be TXT
        if (0 === strncasecmp(PHP_SAPI, 'cli', 3)) {
            $format = 'txt';
        }

        $template = $this->container->getTemplatingService()->getLoader()->load($template, array(
            'bundle'     => 'WebBundle',
            'controller' => 'Exception',
            'format'     => '.'.$format,
        ));

        if (false === $template) {
            throw new \InvalidArgumentException(sprintf('The exception template for format "%s" does not exist.', $format));
        }

        $code      = $exception instanceof HttpException ? $exception->getCode() : 500;
        $text      = Response::$statusTexts[$code];
        $formatter = new ExceptionFormatter($this->container);
        $message   = null === $exception->getMessage() ? 'n/a' : $exception->getMessage();
        $name      = get_class($exception);
        $traces    = $formatter->getTraces($exception, 'html' === $format ? 'html' : 'text');
        $charset   = $this->container->getParameter('kernel.charset');

        $errors = 0;
        foreach ($logs as $log) {
            if ('ERR' === $log['priorityName']) {
                ++$errors;
            }
        }

        $currentContent = '';
        while (false !== $content = ob_get_clean()) {
            $currentContent .= $content; 
        }

        ob_start();
        require $template;
        $content = ob_get_clean();

        $response = $this->container->getResponseService();
        $response->setStatusCode($code);
        $response->setContent($content);

        return $response;
    }
}
