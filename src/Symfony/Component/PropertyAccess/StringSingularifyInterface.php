<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess;

/**
 * Creates singulars from plurals.
 *
 * @author Luis-Ramón López <lrlopez@gmail.com>
 */
interface StringSingularifyInterface
{
    /**
     * Returns the singular form of a word.
     *
     * If the method can't determine the form with certainty, an array of the
     * possible singulars is returned.
     *
     * @param string $plural A word in plural form
     *
     * @return string|array The singular form or an array of possible singular
     *                      forms
     */
    public function singularify($plural);
}
