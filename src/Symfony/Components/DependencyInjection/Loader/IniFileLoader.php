<?php

namespace Symfony\Components\DependencyInjection\Loader;

use Symfony\Components\DependencyInjection\BuilderConfiguration;
use Symfony\Components\DependencyInjection\FileResource;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * IniFileLoader loads parameters from INI files.
 *
 * @package    Symfony
 * @subpackage Components_DependencyInjection
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class IniFileLoader extends FileLoader
{
    /**
     * Loads a resource.
     *
     * @param mixed                $resource       The resource
     * @param Boolean              $main           Whether this is the main load() call
     * @param BuilderConfiguration $configuration  A BuilderConfiguration instance to use for the configuration
     *
     * @return BuilderConfiguration A BuilderConfiguration instance
     *
     * @throws \InvalidArgumentException When ini file is not valid
     */
    public function load($file, $main = true, BuilderConfiguration $configuration = null)
    {
        $path = $this->findFile($file);

        if (null === $configuration) {
            $configuration = new BuilderConfiguration();
        }

        $configuration->addResource(new FileResource($path));

        $result = parse_ini_file($path, true);
        if (false === $result || array() === $result) {
            throw new \InvalidArgumentException(sprintf('The %s file is not valid.', $file));
        }

        if (isset($result['parameters']) && is_array($result['parameters'])) {
            foreach ($result['parameters'] as $key => $value) {
                $configuration->setParameter(strtolower($key), $value);
            }
        }

        return $configuration;
    }
}
