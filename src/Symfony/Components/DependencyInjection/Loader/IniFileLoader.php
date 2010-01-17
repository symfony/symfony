<?php

namespace Symfony\Components\DependencyInjection\Loader;

use Symfony\Components\DependencyInjection\BuilderConfiguration;
use Symfony\Components\DependencyInjection\FileResource;

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
 */
class IniFileLoader extends FileLoader
{
  /**
   * Loads a resource.
   *
   * @param  string $file An INI file path
   *
   * @return BuilderConfiguration A BuilderConfiguration instance
   */
  public function load($file)
  {
    $path = $this->findFile($file);

    $configuration = new BuilderConfiguration();

    $configuration->addResource(new FileResource($path));

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

    return $configuration;
  }
}
