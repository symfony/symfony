<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ResourceWatcher\StateChecker;

/**
 * Resource state checker interface.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
interface StateCheckerInterface
{
    /**
     * Returns tracked resource.
     *
     * @return  ResourceInterface
     */
    function getResource();

    /**
     * Check tracked resource for changes.
     *
     * @return  array
     */
    function getChangeset();
}
