<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Handler;

use Monolog\Handler\ChromePHPHandler as BaseChromePhpHandler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

/**
 * ChromePhpHandler.
 *
 * @author Christophe Coevoet <stof@notk.org>
 */
class ChromePhpHandler extends BaseChromePhpHandler
{
    /**
     * @var array
     */
    private $headers = array();

    /**
     * @var Response
     */
    private $response;

    /**
     * Adds the headers to the response once it's created
     */
    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        if (!preg_match('{\bChrome/\d+[\.\d+]*\b}', $event->getRequest()->headers->get('User-Agent'))) {

            $this->sendHeaders = false;
            $this->headers = array();

            return;
        }

        $this->response = $event->getResponse();
        foreach ($this->headers as $header => $content) {
            $this->response->headers->set($header, $content);
        }
        $this->headers = array();
    }

    /**
     * {@inheritDoc}
     */
    protected function sendHeader($header, $content)
    {
        if (!$this->sendHeaders) {
            return;
        }

        if ($this->response) {
            $this->response->headers->set($header, $content);
        } else {
            $this->headers[$header] = $content;
        }
    }

    /**
     * Override default behavior since we check it in onKernelResponse
     */
    protected function headersAccepted()
    {
        return true;
    }
}
