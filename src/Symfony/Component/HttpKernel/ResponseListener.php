<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel;

use Symfony\Component\HttpKernel\Event\RequestEventArgs;
use Symfony\Component\HttpFoundation\Response;

/**
 * ResponseListener fixes the Response Content-Type.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ResponseListener
{
    protected $charset;

    public function __construct($charset)
    {
        $this->charset = $charset;
    }

    /**
     * Filters the Response.
     *
     * @param RequestEventArgs $eventArgs    A RequestEventArgs instance
     */
    public function filterCoreResponse(RequestEventArgs $eventArgs)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->getRequestType()) {
            return;
        }

        $response = $eventArgs->getResponse();

        if (null === $response->getCharset()) {
            $response->setCharset($this->charset);
        }

        if ($response->headers->has('Content-Type')) {
            return;
        }

        $request = $event->getRequest();
        $format = $request->getRequestFormat();
        if ((null !== $format) && $mimeType = $request->getMimeType($format)) {
            $response->headers->set('Content-Type', $mimeType);
        }
    }
}
