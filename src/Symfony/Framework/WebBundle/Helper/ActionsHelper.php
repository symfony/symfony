<?php

namespace Symfony\Framework\WebBundle\Helper;

use Symfony\Components\Templating\Helper\Helper;
use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Components\OutputEscaper\Escaper;

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
class ActionsHelper extends Helper
{
  protected $container;

  /**
   * Constructor.
   *
   * @param Constructor $container A ContainerInterface instance
   */
  public function __construct(ContainerInterface $container)
  {
    $this->container = $container;
  }

  public function output($controller, array $parameters = array())
  {
    echo $this->render($controller, $parameters);
  }

  public function render($controller, array $parameters = array())
  {
    return $this->container->getControllerLoaderService()->run($controller, Escaper::unescape($parameters))->getContent();
  }

  /**
   * Returns the canonical name of this helper.
   *
   * @return string The canonical name
   */
  public function getName()
  {
    return 'actions';
  }
}
