<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\ClassMapArgument;
use Symfony\Component\DependencyInjection\Attribute\WithKey;
use Symfony\Component\DependencyInjection\Loader\ClassMapLoader;

final class ResolveClassMapsPass extends AbstractRecursivePass
{
    protected function processValue(mixed $value, bool $isRoot = false): mixed
    {
        if (!$value instanceof ClassMapArgument) {
            return parent::processValue($value, $isRoot);
        }

        $classMapLoader = new ClassMapLoader($this->container, new FileLocator());

        $classMap = [];
        foreach ($classMapLoader->load($value->getValues()) as $class => $errorMessage) {
            if (null !== $errorMessage) {
                $this->container->getDefinition($this->currentId)->addError($errorMessage);
                continue;
            }

            if (null !== $key = $this->findKey($class, $value->indexBy)) {
                $classMap[$key] = $class;
            } else {
                $classMap[] = $class;
            }
        }

        return $classMap;
    }

    private function findKey(string $class, ?string $indexBy): string|int|null
    {
        $refClass = $this->container->getReflectionClass($class);

        /** @var list<\ReflectionAttribute<WithKey>> $attributes */
        if ($attributes = $refClass->getAttributes(WithKey::class)) {
            return $attributes[0]->newInstance()->value;
        }

        if (null === $indexBy) {
            return null;
        }

        if ($refClass->hasMethod($indexBy) && ($refMethod = $refClass->getMethod($indexBy))->isPublic() && $refMethod->isStatic()) {
            return $refMethod->invoke(null);
        }

        if ($refClass->hasProperty($indexBy) && ($refProp = $refClass->getProperty($indexBy))->isPublic() && $refProp->isStatic()) {
            return $refProp->getValue();
        }

        if ($refClass->hasConstant($indexBy) && $refClass->getReflectionConstant($indexBy)->isPublic()) {
            return $refClass->getConstant($indexBy);
        }

        return null;
    }
}
