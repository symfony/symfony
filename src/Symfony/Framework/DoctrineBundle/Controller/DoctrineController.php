<?php

namespace Symfony\Framework\DoctrineBundle\Controller;

use Symfony\Framework\WebBundle\Controller;
use Symfony\Components\RequestHandler\Request;
use Symfony\Components\RequestHandler\Exception\NotFoundHttpException;

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
class DoctrineController extends Controller
{
  protected function getManager()
  {
    return $this->container->getDoctrine_ORM_ManagerService();
  }

  public function createQueryBuilder()
  {
    return $this->getManager()->createQueryBuilder();
  }

  public function createQuery($dql = '')
  {
    return $this->getManager()->createQuery($dql);
  }
}
