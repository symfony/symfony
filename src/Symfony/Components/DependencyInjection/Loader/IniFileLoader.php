<?php

namespace Symfony\Components\DependencyInjection\Loader;

use Symfony\Components\DependencyInjection\ContainerBuilder;
use Symfony\Components\DependencyInjection\Resource\FileResource;

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
     * @param mixed            $resource       The resource
     * @param ContainerBuilder $container  A ContainerBuilder instance to use for the configuration
     *
     * @return ContainerBuilder A ContainerBuilder instance
     *
     * @throws \InvalidArgumentException When ini file is not valid
     */
    public function load($file, ContainerBuilder $container = null)
    {
        $path = $this->findFile($file);

        if (null === $container) {
            $container = new ContainerBuilder();
        }

        $container->addResource(new FileResource($path));

        $result = parse_ini_file($path, true);
        if (false === $result || array() === $result) {
            throw new \InvalidArgumentException(sprintf('The %s file is not valid.', $file));
        }

        if (isset($result['parameters']) && is_array($result['parameters'])) {
            foreach ($result['parameters'] as $key => $value) {
                $container->setParameter($key, $value);
            }
        }

        return $container;
    }
}
