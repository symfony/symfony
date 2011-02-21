<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel;

use Symfony\Component\EventDispatcher\EventInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * ResponseListener fixes the Response Content-Type.
 *
 * The filter method must be connected to the core.response event.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
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
     * @param EventInterface $event    An EventInterface instance
     * @param Response       $response A Response instance
     */
    public function filter(EventInterface $event, Response $response)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->get('request_type')) {
            return $response;
        }

        if (null === $response->getCharset()) {
            $response->setCharset($this->charset);
        }

        if ($response->headers->has('Content-Type')) {
            return $response;
        }

        $request = $event->get('request');
        $format = $request->getRequestFormat();
        if ((null !== $format) && $mimeType = $request->getMimeType($format)) {
            $response->headers->set('Content-Type', $mimeType);
        }

        return $response;
    }
}
