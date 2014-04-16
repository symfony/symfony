<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader;

use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * IniFileLoader loads parameters from INI files.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class IniFileLoader extends FileLoader
{
    /**
     * Loads a resource.
     *
     * @param mixed  $file The resource
     * @param string $type The resource type
     *
     * @throws InvalidArgumentException When ini file is not valid
     */
    public function load($file, $type = null)
    {
        $path = $this->locator->locate($file);

        $this->container->addResource(new FileResource($path));

        $result = parse_ini_file($path, true);
        if (false === $result || array() === $result) {
            throw new InvalidArgumentException(sprintf('The "%s" file is not valid.', $file));
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
     * @param mixed  $resource A resource
     * @param string $type     The resource type
     *
     * @return bool    true if this class supports the given resource, false otherwise
     */
    public function supports($resource, $type = null)
    {
        return is_string($resource) && 'ini' === pathinfo($resource, PATHINFO_EXTENSION);
    }
}
