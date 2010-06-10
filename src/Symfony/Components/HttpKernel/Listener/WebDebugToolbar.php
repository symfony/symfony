<?php

namespace Symfony\Components\HttpKernel\Listener;

use Symfony\Components\EventDispatcher\EventDispatcher;
use Symfony\Components\EventDispatcher\Event;
use Symfony\Components\HttpKernel\Request;
use Symfony\Components\HttpKernel\Response;
use Symfony\Components\HttpKernel\HttpKernelInterface;
use Symfony\Components\HttpKernel\Profiler\Profiler;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * WebDebugToolbar.
 *
 * @package    Symfony
 * @subpackage Framework_ProfilerBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class WebDebugToolbar
{
    protected $profiler;

    public function __construct(Profiler $profiler)
    {
        $this->profiler = $profiler;
    }

    /**
     * Registers a core.response listener.
     *
     * @param Symfony\Components\EventDispatcher\EventDispatcher $dispatcher An EventDispatcher instance
     */
    public function register(EventDispatcher $dispatcher)
    {
        $dispatcher->connect('core.response', array($this, 'handle'));
    }

    public function handle(Event $event, Response $response)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->getParameter('request_type')) {
            return $response;
        }

        $request = $event->getParameter('request');

        if ('3' === substr($response->getStatusCode(), 0, 1)
            || ($response->headers->has('Content-Type') && false === strpos($response->headers->get('Content-Type'), 'html'))
            || 'html' !== $request->getRequestFormat()
            || $request->isXmlHttpRequest()
        ) {
            return $response;
        }

        $response->setContent($this->injectToolbar($request, $response));

        return $response;
    }

    /**
     * Injects the web debug toolbar into a given HTML string.
     *
     * @param string $content The HTML content
     *
     * @return Response A Response instance
     */
    protected function injectToolbar(Request $request, Response $response)
    {
        $data = '';
        foreach ($this->profiler->getCollectors() as $name => $collector) {
            $data .= $collector->getSummary();
        }

        $position = false === strpos($request->headers->get('user-agent'), 'Mobile') ? 'fixed' : 'absolute';

        $toolbar = <<<EOF

<!-- START of Symfony 2 Web Debug Toolbar -->
<div style="clear: both; height: 40px;"></div>
<div style="position: $position; bottom: 0px; left:0; z-index: 6000000; width: 100%; background: #dde4eb; border-top: 1px solid #bbb; padding: 5px; margin: 0; font: 11px Verdana, Arial, sans-serif; color: #222;">
    $data
</div>
<!-- END of Symfony 2 Web Debug Toolbar -->

EOF;

        $toolbar = "\n".str_replace("\n", '', $toolbar)."\n";
        $count = 0;
        $content = str_ireplace('</body>', $toolbar.'</body>', $response->getContent(), $count);
        if (!$count) {
            $content .= $toolbar;
        }

        return $content;
    }
}
