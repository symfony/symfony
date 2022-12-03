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
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * ChromePhpHandler.
 *
 * @author Christophe Coevoet <stof@notk.org>
 *
 * @final
 */
class ChromePhpHandler extends BaseChromePhpHandler
{
    private array $headers = [];
    private Response $response;

    /**
     * Adds the headers to the response once it's created.
     */
    public function onKernelResponse(ResponseEvent $event)
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!preg_match(static::USER_AGENT_REGEX, $event->getRequest()->headers->get('User-Agent'))) {
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

    protected function sendHeader($header, $content): void
    {
        if (!self::$sendHeaders) {
            return;
        }

        if (isset($this->response)) {
            $this->response->headers->set($header, $content);
        } else {
            $this->headers[$header] = $content;
        }
    }

    /**
     * Override default behavior since we check it in onKernelResponse.
     */
    protected function headersAccepted(): bool
    {
        return true;
    }
}
