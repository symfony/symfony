<?php

namespace Symfony\Foundation;

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * ClassLoader implementation that implements the technical interoperability
 * standards for PHP 5.3 namespaces and class names.
 *
 * Based on http://groups.google.com/group/php-standards/web/final-proposal
 *
 * Example usage:
 *
 *     [php]
 *     $loader = new ClassLoader();
 *     $loader->registerNamespace('Symfony', __DIR__.'/..');
 *     $loader->register();
 *
 * @author Jonathan H. Wage <jonwage@gmail.com>
 * @author Roman S. Borschel <roman@code-factory.org>
 * @author Matthew Weier O'Phinney <matthew@zend.com>
 * @author Kris Wallsmith <kris.wallsmith@gmail.com>
 * @author Fabien Potencier <fabien.potencier@symfony-project.org>
 */
class ClassLoader
{
  protected $namespaces = array();

  /**
   * Creates a new loader for classes of the specified namespace.
   *
   * @param string $namespace   The namespace to use
   * @param string $includePath The path to the namespace
   */
  public function registerNamespace($namespace, $includePath = null)
  {
    if (!isset($this->namespaces[$namespace]))
    {
      $this->namespaces[$namespace] = $includePath;
    }
    else
    {
      if (!is_array($this->namespaces[$namespace]))
      {
        $this->namespaces[$namespace] = array($this->namespaces[$namespace]);
      }

      $this->namespaces[$namespace][] = $includePath;
    }
  }

  /**
   * Installs this class loader on the SPL autoload stack.
   */
  public function register()
  {
    spl_autoload_register(array($this, 'loadClass'));
  }

  /**
   * Uninstalls this class loader from the SPL autoloader stack.
   */
  public function unregister()
  {
    spl_autoload_unregister(array($this, 'loadClass'));
  }

  /**
   * Loads the given class or interface.
   *
   * @param string $className The name of the class to load
   */
  public function loadClass($className)
  {
    $vendor = substr($className, 0, stripos($className, '\\'));
    if ($vendor || isset($this->namespaces['']))
    {
      $fileName = '';
      $namespace = '';
      if (false !== ($lastNsPos = strripos($className, '\\')))
      {
        $namespace = substr($className, 0, $lastNsPos);
        $className = substr($className, $lastNsPos + 1);
        $fileName = str_replace('\\', DIRECTORY_SEPARATOR, $namespace).DIRECTORY_SEPARATOR;
      }
      $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className).'.php';

      if (null !== $this->namespaces[$vendor])
      {
        if (is_array($this->namespaces[$vendor]))
        {
          foreach ($this->namespaces[$vendor] as $dir)
          {
            if (!file_exists($dir.DIRECTORY_SEPARATOR.$fileName))
            {
              continue;
            }

            require $dir.DIRECTORY_SEPARATOR.$fileName;

            break;
          }
        }
        else
        {
          require $this->namespaces[$vendor].DIRECTORY_SEPARATOR.$fileName;
        }
      }
      else
      {
        require $fileName;
      }
    }
  }
}
