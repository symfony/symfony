<?php

namespace Symfony\Components\Templating\Loader;

use Symfony\Components\Templating\Storage\Storage;
use Symfony\Components\Templating\Storage\FileStorage;

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * CacheLoader is a loader that caches other loaders responses
 * on the filesystem.
 *
 * This cache only caches on disk to allow PHP accelerators to cache the opcodes.
 * All other mecanism would imply the use of `eval()`.
 *
 * @package    symfony
 * @subpackage templating
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class CacheLoader extends Loader
{
  protected $loader;
  protected $dir;

  /**
   * Constructor.
   *
   * @param Loader $loader A Loader instance
   * @param string           $dir    The directory where to store the cache files
   */
  public function __construct(Loader $loader, $dir)
  {
    $this->loader = $loader;
    $this->dir = $dir;

    parent::__construct();
  }

  /**
   * Loads a template.
   *
   * @param string $template The logical template name
   * @param array  $options  An array of options
   *
   * @return Storage|Boolean false if the template cannot be loaded, a Storage instance otherwise
   */
  public function load($template, array $options = array())
  {
    $options = $this->mergeDefaultOptions($options);

    $path = $this->dir.DIRECTORY_SEPARATOR.md5($template.$options['renderer']).'.tpl';

    if ($this->loader instanceof CompilableLoaderInterface)
    {
      $options['renderer'] = 'php';
    }

    if (file_exists($path))
    {
      if ($this->debugger)
      {
        $this->debugger->log(sprintf('Fetching template "%s" from cache', $template));
      }

      return new FileStorage($path, $options['renderer']);
    }

    if (false === $content = $this->loader->load($template, $options))
    {
      return false;
    }

    if ($this->loader instanceof CompilableLoaderInterface)
    {
      $content = $this->loader->compile($content);
    }

    file_put_contents($path, $content);

    if ($this->debugger)
    {
      $this->debugger->log(sprintf('Storing template "%s" in cache', $template));
    }

    return new FileStorage($path, $options['renderer']);
  }
}
