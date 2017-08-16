<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Command;

/**
 * Enables lazy-loading capabilities for a Command by exposing its default name.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
interface DefaultNameProviderInterface
{
    /**
     * @return string The name to use by default for calling the Command
     */
    public static function getDefaultName();
}
