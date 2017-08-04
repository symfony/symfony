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

/**
 * @author David Badura <d.a.badura@gmail.com>
 */
interface NamingStrategyInterface
{
    /**
     * Returns all possible getters.
     *
     * @param string $class
     * @param string $property
     *
     * @return array
     */
    public function getGetters(string $class, string $property): array;

    /**
     * Returns all possible setters.
     *
     * @param string $class
     * @param string $property
     *
     * @return array
     */
    public function getSetters(string $class, string $property): array;

    /**
     * Returns all possible adders and removers.
     *
     * @param string $class
     * @param string $property
     *
     * @return array
     */
    public function getAddersAndRemovers(string $class, string $property): array;
}
