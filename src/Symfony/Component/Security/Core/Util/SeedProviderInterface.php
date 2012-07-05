<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Util;

/**
 * Seed Provider Interface.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface SeedProviderInterface
{
    /**
     * Loads the initial seed.
     *
     * Whatever is returned from this method, it should not be guessable.
     *
     * @return array of the format array(string, DateTime) where string is the
     *               initial seed, and DateTime is the last time it was updated
     */
    function loadSeed();

    /**
     * Updates the seed.
     *
     * @param string $seed
     */
    function updateSeed($seed);
}
