<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Controller;

use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\Debug\ExceptionFlattener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * PreviewErrorController can be used to test error pages.
 *
 * It will create a test exception and forward it to another controller.
 *
 * @author Matthias Pigulla <mp@webfactory.de>
 */
class PreviewErrorController
{
    protected $kernel;
    protected $controller;
    protected $flattener;

    public function __construct(HttpKernelInterface $kernel, $controller, ExceptionFlattener $flattener = null)
    {
        $this->kernel = $kernel;
        $this->controller = $controller;
        $this->flattener = $flattener;
    }

    public function previewErrorPageAction(Request $request, $code)
    {
        $e = new \Exception('Something has intentionally gone wrong.');
        $exception = null === $this->flattener ? FlattenException::create($e, $code) : $this->flattener->flatten($e);
        $exception->setStatusCode($code);

        /*
         * This Request mimics the parameters set by
         * \Symfony\Component\HttpKernel\EventListener\ExceptionListener::duplicateRequest, with
         * the additional "showException" flag.
         */

        $subRequest = $request->duplicate(null, null, array(
            '_controller' => $this->controller,
            'exception' => $exception,
            'logger' => null,
            'format' => $request->getRequestFormat(),
            'showException' => false,
        ));

        return $this->kernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
    }
}
