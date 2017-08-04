<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\NamingStrategy;

use Symfony\Component\Inflector\Inflector;

/**
 * @author David Badura <d.a.badura@gmail.com>
 */
class CamelCaseStrategy implements NamingStrategyInterface
{
    /**
     * {@inheritdoc}
     */
    public function getGetters(string $class, string $property): array
    {
        $camelProp = self::camelize($property);

        return array(
            'get'.$camelProp,
            lcfirst($camelProp), // jQuery style, e.g. read: last(), write: last($item)
            'is'.$camelProp,
            'has'.$camelProp,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getSetters(string $class, string $property): array
    {
        $camelized = self::camelize($property);

        return array(
            'set'.$camelized,
            lcfirst($camelized), // jQuery style, e.g. read: last(), write: last($item)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getAddersAndRemovers(string $class, string $property): array
    {
        $singulars = (array) Inflector::singularize(self::camelize($property));

        return array_map(function ($singular) {
            return array('add'.$singular, 'remove'.$singular);
        }, $singulars);
    }

    private static function camelize(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
    }
}
