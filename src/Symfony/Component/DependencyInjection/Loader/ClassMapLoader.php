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

use Symfony\Component\Config\Resource\GlobResource;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

class ClassMapLoader extends FileLoader
{
    public function load(mixed $resource, ?string $type = null): mixed
    {
        $parameterBag = $this->container->getParameterBag();

        $pattern = $parameterBag->unescapeValue($parameterBag->resolveValue($resource['path']));
        $namespace = $resource['namespace'];

        $classes = [];
        foreach ($this->glob($pattern, true, $globResource) as $path => $info) {
            if (!str_ends_with($path, '.php')) {
                continue;
            }

            $prefixLen ??= \strlen($globResource->getPrefix());
            $class = $namespace.ltrim(str_replace('/', '\\', substr($path, $prefixLen, -4)), '\\');

            if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+(?:\\\\[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+)*+$/', $class)) {
                continue;
            }

            try {
                $r = $this->container->getReflectionClass($class);
            } catch (\ReflectionException $e) {
                $classes[$class] = $e->getMessage();
                continue;
            }
            // check to make sure the expected class exists
            if (!$r) {
                throw new InvalidArgumentException(sprintf('Expected to find class "%s" in file "%s" while importing class names from resource "%s", but it was not found! Check the namespace prefix used with the resource.', $class, $path, $pattern));
            }

            if ($r->isInterface() || $r->isAbstract() || $r->isTrait()) {
                continue;
            }

            if (
                isset($resource['instance_of']) && !$r->isSubclassOf($resource['instance_of'])
                || isset($resource['with_attribute']) && !$r->getAttributes($resource['with_attribute'])
            ) {
                continue;
            }

            $classes[$class] = null;
        }

        // track only for new & removed files
        if ($globResource instanceof GlobResource) {
            $this->container->addResource($globResource);
        } else {
            foreach ($globResource as $path) {
                $this->container->fileExists($path, false);
            }
        }

        return $classes;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return 'class-map' === $type && \is_array($resource) && isset($resource['path'], $resource['namespace']);
    }
}
