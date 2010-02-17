<?php

namespace Symfony\Components\Routing\Loader;

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * LoaderInterface is the interface that all loaders classes must implement.
 *
 * @package    symfony
 * @subpackage routing
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface LoaderInterface
{
  /**
   * Loads a resource.
   *
   * @param  mixed $resource A resource
   *
   * @return RouteCollection A RouteCollection instance
   */
  function load($resource);
}
