<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Expression;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
interface ValueInterface
{
    /**
     * Renders string representation of expression.
     *
     * @return string
     */
    function render();

    /**
     * Renders string representation of pattern.
     *
     * @return string
     */
    function renderPattern();

    /**
     * Returns value case sensitivity.
     *
     * @return bool
     */
    function isCaseSensitive();

    /**
     * Returns expression type.
     *
     * @return int
     */
    function getType();
}
