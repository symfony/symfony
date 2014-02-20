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
    /**
     * @var integer
     */
    const IMPLICIT = 1;

    const NONE = 2;

    const TRAVERSE = 4;

    const STOP_RECURSION = 8;

    private function __construct()
    {
    }
}
