<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Mapping;

/**
 * Specifies whether class' property constraints should be merged with
 * or override property constraints of a parent class.
 *
 * Overriding property constraints works for properties that override parent property of same name.
 *
 * @author Przemysław Bogusz <przemyslaw.bogusz@tubotax.pl>
 */
final class OverridingPropertyConstraintsStrategy
{
    /**
     * Nothing explicitly set.
     * In case of property, rely on class strategy.
     * In case of class, inherit strategy from parent class.
     */
    public const NONE = 0;

    /**
     * Specifies that class' property constraints should be merged with constraints of a parent class.
     */
    public const DISABLED = 1;

    /**
     * Specifies that class' property constraints should override constraints of a parent class.
     */
    public const ENABLED = 2;

    /**
     * Not instantiable.
     */
    private function __construct()
    {
    }
}
