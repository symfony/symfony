<?php

namespace Symfony\Components\Templating\Renderer;

use Symfony\Components\Templating\Engine;

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Renderer is the base class for all template renderer.
 *
 * @package    symfony
 * @subpackage templating
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class Renderer implements RendererInterface
{
  protected $engine;

  /**
   * Sets the template engine associated with this renderer.
   *
   * @param Engine $engine A Engine instance
   */
  public function setEngine(Engine $engine)
  {
    $this->engine = $engine;
  }

  /**
   * Forwards the call to the associated template instance.
   *
   * @param string $method    The method name
   * @param array  $arguments The array of arguments
   *
   * @return mixed The return value returned by the associated template instance method
   */
  public function __call($method, $arguments)
  {
    if (!method_exists($this->engine, $method))
    {
      throw new \RuntimeException(sprintf('Call to undefined method %s::%s().', get_class($this->engine), $method));
    }

    return call_user_func_array(array($this->engine, $method), $arguments);
  }

  /**
   * Forwards the call to the associated template instance.
   *
   * @param string $name The property name
   *
   * @return mixed The value returned by the associated template instance
   */
  public function __get($name)
  {
    return $this->engine->$name;
  }
}
