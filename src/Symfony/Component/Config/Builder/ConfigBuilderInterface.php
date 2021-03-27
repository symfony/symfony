<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Builder;

/**
 * A ConfigBuilder provides helper methods to build a large complex array.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * @experimental in 5.3
 */
interface ConfigBuilderInterface
{
    /**
     * Gets all configuration represented as an array.
     */
    public function toArray(): array;

    /**
     * Gets the alias for the extension which config we are building.
     */
    public function getExtensionAlias(): string;
}
