<?php

namespace Symfony\Bundle\WebProfilerBundle\Controller;

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\HttpKernel\Exception\FlattenException;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\ExceptionController as BaseExceptionController;

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
class ExceptionController extends BaseExceptionController
{
    /**
     * {@inheritdoc}
     */
    public function showAction(FlattenException $exception, DebugLoggerInterface $logger = null, $format = 'html')
    {
        $template = $this->container->get('kernel')->isDebug() ? 'exception' : 'error';
        $code = $this->getStatusCode($exception);

        return $this->container->get('templating')->renderResponse(
            'FrameworkBundle:Exception:'.$template.'.twig.html',
            array(
                'status_code'    => $code,
                'status_text'    => Response::$statusTexts[$code],
                'exception'      => $exception,
                'logger'         => null,
                'currentContent' => '',
            )
        );
    }
}
