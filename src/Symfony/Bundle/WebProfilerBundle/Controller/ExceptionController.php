<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Controller;

use Symfony\Component\HttpKernel\Exception\FlattenException;
use Symfony\Component\HttpFoundation\Response;

/**
 * ExceptionController.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ExceptionController
{
    protected $twig;
    protected $debug;

    public function __construct(\Twig_Environment $twig, $debug)
    {
        $this->twig = $twig;
        $this->debug = $debug;
    }

    /**
     * Converts an Exception to a Response.
     *
     * @param FlattenException $exception A FlattenException instance
     *
     * @return Response
     */
    public function showAction(FlattenException $exception)
    {
        $code = $exception->getStatusCode();

        return new Response($this->twig->render(
            '@Twig/Exception/'.($this->debug ? 'exception' : 'error').'.html.twig',
            array(
                'status_code'    => $code,
                'status_text'    => Response::$statusTexts[$code],
                'exception'      => $exception,
                'logger'         => null,
                'currentContent' => '',
            )
        ));
    }
}
