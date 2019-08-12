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

use Monolog\Handler\FirePHPHandler as BaseFirePHPHandler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

/**
 * FirePHPHandler.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 *
 * @final since Symfony 4.3
 */
class FirePHPHandler extends BaseFirePHPHandler
{
    private $headers = [];

    /**
     * @var Response
     */
    private $response;

    /**
     * Adds the headers to the response once it's created.
     */
    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!preg_match('{\bFirePHP/\d+\.\d+\b}', $request->headers->get('User-Agent'))
            && !$request->headers->has('X-FirePHP-Version')) {
            self::$sendHeaders = false;
            $this->headers = [];

            return;
        }

        $this->response = $event->getResponse();
        foreach ($this->headers as $header => $content) {
            $this->response->headers->set($header, $content);
        }
        $this->headers = [];
    }

    /**
     * {@inheritdoc}
     */
    protected function sendHeader($header, $content)
    {
        if (!self::$sendHeaders) {
            return;
        }

        if ($this->response) {
            $this->response->headers->set($header, $content);
        } else {
            $this->headers[$header] = $content;
        }
    }

    /**
     * Override default behavior since we check the user agent in onKernelResponse.
     *
     * @return bool
     */
    protected function headersAccepted()
    {
        return true;
    }
}
