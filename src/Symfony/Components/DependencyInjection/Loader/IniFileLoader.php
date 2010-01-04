<?php

namespace Symfony\Components\DependencyInjection\Loader;

use Symfony\Components\DependencyInjection\BuilderConfiguration;

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * IniFileLoader loads parameters from INI files.
 *
 * @package    symfony
 * @subpackage dependency_injection
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @version    SVN: $Id$
 */
class IniFileLoader extends FileLoader
{
  public function load($files)
  {
    if (!is_array($files))
    {
      $files = array($files);
    }

    $configuration = new BuilderConfiguration();

    foreach ($files as $file)
    {
      $path = $this->getAbsolutePath($file);
      if (!file_exists($path))
      {
        throw new \InvalidArgumentException(sprintf('The %s file does not exist.', $file));
      }

      $result = parse_ini_file($path, true);
      if (false === $result || array() === $result)
      {
        throw new \InvalidArgumentException(sprintf('The %s file is not valid.', $file));
      }

      if (isset($result['parameters']) && is_array($result['parameters']))
      {
        foreach ($result['parameters'] as $key => $value)
        {
          $configuration->setParameter(strtolower($key), $value);
        }
      }
    }

    return $configuration;
  }
}
