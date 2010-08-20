<?php

namespace Symfony\Component\DependencyInjection\Loader;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Resource\FileResource;

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
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class IniFileLoader extends FileLoader
{
    /**
     * Loads a resource.
     *
     * @param mixed            $resource       The resource
     *
     * @throws \InvalidArgumentException When ini file is not valid
     */
    public function load($file)
    {
        $path = $this->findFile($file);

        $this->container->addResource(new FileResource($path));

        $result = parse_ini_file($path, true);
        if (false === $result || array() === $result) {
            throw new \InvalidArgumentException(sprintf('The %s file is not valid.', $file));
        }

        if (isset($result['parameters']) && is_array($result['parameters'])) {
            foreach ($result['parameters'] as $key => $value) {
                $this->container->setParameter($key, $value);
            }
        }
    }

    /**
     * Returns true if this class supports the given resource.
     *
     * @param  mixed $resource A resource
     *
     * @return Boolean true if this class supports the given resource, false otherwise
     */
    public function supports($resource)
    {
        return is_string($resource) && 'ini' === pathinfo($resource, PATHINFO_EXTENSION);
    }
}
