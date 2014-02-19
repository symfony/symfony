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
 * @since  %%NextVersion%%
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TraversalStrategy
{
    const IMPLICIT = 0;

    const NONE = 1;

    const TRAVERSE = 2;

    const RECURSIVE = 4;

    const IGNORE_NON_TRAVERSABLE = 8;

    private function __construct()
    {
    }
}
