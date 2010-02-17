<?php

namespace Symfony\Framework\WebBundle\Helper;

use Symfony\Components\Templating\Helper\Helper;
use Symfony\Framework\WebBundle\User;

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
class UserHelper extends Helper
{
  protected $user;

  /**
   * Constructor.
   *
   * @param Request $request A Request instance
   */
  public function __construct(User $user)
  {
    $this->user = $user;
  }

  /**
   * Returns a user attribute
   *
   * @param string $name    The attribute name
   * @param mixed  $default The defaut value
   *
   * @return mixed
   */
  public function getAttribute($name, $default = null)
  {
    return $this->user->getAttribute($name, $default);
  }

  /**
   * Returns the user culture
   *
   * @return string $culture
   */
  public function getCulture()
  {
    return $this->user->getCulture();
  }

  public function getFlash($name, $default = null)
  {
    return $this->user->getFlash($name, $default);
  }

  public function hasFlash($name)
  {
    return $this->user->hasFlash($name);
  }

  /**
   * Returns the canonical name of this helper.
   *
   * @return string The canonical name
   */
  public function getName()
  {
    return 'user';
  }
}
