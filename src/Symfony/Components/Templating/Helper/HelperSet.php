<?php

namespace Symfony\Components\Templating\Helper;

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
 * HelperSet represents a set of helpers to be used with a templating engine.
 *
 * @package    symfony
 * @subpackage templating
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @version    SVN: $Id$
 */
class HelperSet
{
  protected
    $helpers = array(),
    $engine  = null;

  public function __construct(array $helpers = array())
  {
    foreach ($helpers as $alias => $helper)
    {
      $this->set($helper, is_int($alias) ? null : $alias);
    }
  }

  /**
   * Sets a helper.
   *
   * @param HelperInterface $value The helper instance
   * @param string                    $alias An alias
   */
  public function set(HelperInterface $helper, $alias = null)
  {
    $this->helpers[$helper->getName()] = $helper;
    if (null !== $alias)
    {
      $this->helpers[$alias] = $helper;
    }

    $helper->setHelperSet($this);
  }

  /**
   * Returns true if the helper if defined.
   *
   * @param string  $name The helper name
   *
   * @return Boolean true if the helper is defined, false otherwise
   */
  public function has($name)
  {
    return isset($this->helpers[$name]);
  }

  /**
   * Gets a helper value.
   *
   * @param string $name The helper name
   *
   * @return HelperInterface The helper instance
   *
   * @throws \InvalidArgumentException if the helper is not defined
   */
  public function get($name)
  {
    if (!$this->has($name))
    {
      throw new \InvalidArgumentException(sprintf('The helper "%s" is not defined.', $name));
    }

    return $this->helpers[$name];
  }

  /**
   * Sets the template engine associated with this helper set.
   *
   * @param Engine $engine A Engine instance
   */
  public function setEngine(Engine $engine = null)
  {
    $this->engine = $engine;
  }

  /**
   * Gets the template engine associated with this helper set.
   *
   * @return Engine A Engine instance
   */
  public function getEngine()
  {
    return $this->engine;
  }
}
