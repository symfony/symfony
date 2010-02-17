<?php

namespace Symfony\Framework\WebBundle;

use Symfony\Components\EventDispatcher\EventDispatcher;
use Symfony\Components\EventDispatcher\Event;
use Symfony\Framework\WebBundle\Session\SessionInterface;

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
class User
{
  protected $session;
  protected $culture;
  protected $attributes;
  protected $oldFlashes;

  /**
   * Initialize the user class
   *
   * @param EventDispatcher  $dispatcher A EventDispatcher instance
   * @param SessionInterface $session    A SessionInterface instance
   * @param array            $options    An array of options
   */
  public function __construct(EventDispatcher $dispatcher, SessionInterface $session, $options = array())
  {
    $this->dispatcher = $dispatcher;
    $this->session    = $session;

    $this->setAttributes($session->read('_user', array(
      '_flash'   => array(),
      '_culture' => isset($options['default_culture']) ? $options['default_culture'] : 'en',
    )));

    // flag current flash to be removed at shutdown
    $this->oldFlashes = array_flip(array_keys($this->getFlashMessages()));
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
    return array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
  }

  /**
   * Sets an user attribute
   * 
   * @param string $name
   * @param mixed $value
   */
  public function setAttribute($name, $value)
  {
    $this->attributes[$name] = $value;
  }

  /**
   * Returns user attributes
   *
   * @return array User attributes
   */
  public function getAttributes()
  {
    return $this->attributes;
  }

  /**
   * Sets user attributes
   *
   * @param array Attributes
   */
  public function setAttributes($attributes)
  {
    $this->attributes = $attributes;
  }

  /**
   * Returns the user culture
   *
   * @return string $culture
   */
  public function getCulture()
  {
    return $this->getAttribute('_culture');
  }

  /**
   * Sets the user culture
   * 
   * @param string $culture 
   */
  public function setCulture($culture)
  {
    if ($this->culture != $culture)
    {
      $this->setAttribute('_culture', $culture);

      $this->dispatcher->notify(new Event($this, 'user.change_culture', array('culture' => $culture)));
    }
  }

  public function getFlashMessages()
  {
    return $this->attributes['_flash'];
  }

  public function setFlashMessages($values)
  {
    $this->attributes['_flash'] = $values;
  }

  public function getFlash($name, $default = null)
  {
    return $this->hasFlash($name) ? $this->attributes['_flash'][$name] : $default;
  }

  public function setFlash($name, $value)
  {
    $this->attributes['_flash'][$name] = $value;
    unset($this->oldFlashes[$name]);
  }

  public function hasFlash($name)
  {
    return array_key_exists($name, $this->attributes['_flash']);
  }

  public function __destruct()
  {
    $this->attributes['_flash'] = array_diff_key($this->attributes['_flash'], $this->oldFlashes);

    $this->session->write('_user', $this->attributes);
  }
}
