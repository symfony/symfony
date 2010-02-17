<?php

namespace Symfony\Framework\WebBundle\Debug\DataCollector;

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
interface DataCollectorInterface
{
  public function setCollectorManager(DataCollectorManager $manager);

  public function collect();

  public function getName();
}
