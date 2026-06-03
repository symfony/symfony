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
 * Specifies how the auto-mapping feature should behave.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
final class AutoMappingStrategy
{
    /**
     * Nothing explicitly set, rely on auto-mapping configured regex.
     */
    public const NONE = 0;

    /**
     * Explicitly enabled.
     */
    public const ENABLED = 1;

    /**
     * Explicitly disabled.
     */
    public const DISABLED = 2;

    /**
     * Not instantiable.
     */
    private function __construct()
    {
    }
}
