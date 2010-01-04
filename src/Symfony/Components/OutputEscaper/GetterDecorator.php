<?php

namespace Symfony\Components\OutputEscaper;

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Abstract output escaping decorator class for "getter" objects.
 *
 * @see        Escaper
 * @package    symfony
 * @subpackage output_escaper
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Mike Squire <mike@somosis.co.uk>
 * @version    SVN: $Id: GetterDecorator.class.php 15624 2009-02-19 10:53:30Z fabien $
 */
abstract class GetterDecorator extends Escaper
{
  /**
   * Returns the raw, unescaped value associated with the key supplied.
   *
   * The key might be an index into an array or a value to be passed to the 
   * decorated object's get() method.
   *
   * @param  string $key  The key to retrieve
   *
   * @return mixed The value
   */
  public abstract function getRaw($key);

  /**
   * Returns the escaped value associated with the key supplied.
   *
   * Typically (using this implementation) the raw value is obtained using the
   * {@link getRaw()} method, escaped and the result returned.
   *
   * @param  string $key             The key to retieve
   * @param  string $escapingMethod  The escaping method (a PHP function) to use
   *
   * @return mixed The escaped value
   */
  public function get($key, $escapingMethod = null)
  {
    if (!$escapingMethod)
    {
      $escapingMethod = $this->escapingMethod;
    }

    return Escaper::escape($escapingMethod, $this->getRaw($key));
  }
}
