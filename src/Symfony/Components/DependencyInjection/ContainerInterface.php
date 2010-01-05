<?php

namespace Symfony\Components\DependencyInjection;

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * ContainerInterface is the interface implemented by service container classes.
 *
 * @package    symfony
 * @subpackage dependency_injection
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface ContainerInterface
{
  public function setParameters(array $parameters);

  public function addParameters(array $parameters);

  public function getParameters();

  public function getParameter($name);

  public function setParameter($name, $value);

  public function hasParameter($name);

  public function setService($id, $service);

  public function getService($id);

  public function hasService($name);
}
