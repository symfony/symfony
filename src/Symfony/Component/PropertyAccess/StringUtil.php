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

use Symfony\Component\Inflector\Inflector;

/**
 * Creates singulars from plurals.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated since version 3.1, to be removed in 4.0. Use {@see Symfony\Component\Inflector\Inflector} instead.
 */
class StringUtil
{
    /**
     * This class should not be instantiated.
     */
    private function __construct()
    {
    }

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
     *
     * @deprecated since version 3.1, to be removed in 4.0. Use {@see Symfony\Component\Inflector\Inflector::singularize} instead.
     */
    public static function singularify($plural)
    {
        @trigger_error('StringUtil::singularify() is deprecated since version 3.1 and will be removed in 4.0. Use Symfony\Component\Inflector\Inflector::singularize instead.', E_USER_DEPRECATED);

        return Inflector::singularize($plural);
    }
}
