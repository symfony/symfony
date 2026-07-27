<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Debug\Section;

/**
 * Collects the classes mapped by Serializer/Validator metadata loaders.
 *
 * @internal
 */
trait MappedClassesTrait
{
    /**
     * @param list<object> $loaders
     *
     * @return list<class-string>
     */
    private function getMappedClasses(array $loaders): array
    {
        $classes = [];
        foreach ($loaders as $loader) {
            if (method_exists($loader, 'getMappedClasses')) {
                foreach ($loader->getMappedClasses() as $class) {
                    $classes[$class] = true;
                }
            }

            if (method_exists($loader, 'getLoaders')) {
                foreach ($this->getMappedClasses($loader->getLoaders()) as $class) {
                    $classes[$class] = true;
                }
            }
        }

        $classes = array_keys($classes);
        sort($classes);

        return $classes;
    }
}
