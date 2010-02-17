<?php

namespace Symfony\Framework\WebBundle\Debug;

use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Components\EventDispatcher\Event;
use Symfony\Components\RequestHandler\Response;
use Symfony\Framework\WebBundle\Debug\DataCollector\DataCollectorManager;

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * 
 *
 * @package    symfony
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class WebDebugToolbar
{
  protected $container;
  protected $collectorManager;

  public function __construct(ContainerInterface $container, DataCollectorManager $collectorManager)
  {
    $this->container = $container;
    $this->collectorManager = $collectorManager;
  }

  public function register()
  {
    $this->container->getEventDispatcherService()->connect('core.response', array($this, 'handle'));
  }

  public function handle(Event $event, Response $response)
  {
    if (!$event->getParameter('main_request'))
    {
      return $response;
    }

    $request = $this->container->getRequestService();

    if (
      '3' === substr($response->getStatusCode(), 0, 1)
      ||
      ($response->hasHeader('Content-Type') && false === strpos($response->getHeader('Content-Type'), 'html'))
      ||
      'html' !== $request->getRequestFormat()
      ||
      $request->isXmlHttpRequest()
    )
    {
      return $response;
    }

    $response->setContent($this->injectToolbar($response));

    return $response;
  }

  /**
   * Injects the web debug toolbar into a given HTML string.
   *
   * @param string $content The HTML content
   *
   * @return Response A Response instance
   */
  protected function injectToolbar(Response $response)
  {
    $data = '';
    foreach ($this->collectorManager->getCollectors() as $name => $collector)
    {
      $data .= $collector->getSummary();
    }

    $toolbar = <<<EOF

<!-- START of Symfony 2 Web Debug Toolbar -->
<div style="clear: both; height: 40px;"></div>
<div style="position: fixed; bottom: 0px; left:0; z-index: 6000000; width: 100%; background: #dde4eb; border-top: 1px solid #bbb; padding: 5px; margin: 0; font: 11px Verdana, Arial, sans-serif; color: #222;">
  $data
</div>
<!-- END of Symfony 2 Web Debug Toolbar -->

EOF;

    $toolbar = "\n".str_replace("\n", '', $toolbar)."\n";
    $count = 0;
    $content = str_ireplace('</body>', $toolbar.'</body>', $response->getContent(), $count);
    if (!$count)
    {
      $content .= $toolbar;
    }

    return $content;
  }
}
