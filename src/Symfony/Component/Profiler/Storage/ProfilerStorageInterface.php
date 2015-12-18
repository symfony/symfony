<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Profiler\Storage;

use Symfony\Component\Profiler\Profile;

/**
 * ProfilerStorageInterface.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jelte Steijaert <jelte@khepri.be>
 */
interface ProfilerStorageInterface
{
    /**
     * Finds profiler tokens for the given criteria.
     *
     * @param array    $criteria The criteria to find profiles
     * @param string   $limit    The maximum number of tokens to return
     * @param int|null $start    The start date to search from
     * @param int|null $end      The end date to search to
     *
     * @return array An array of tokens
     *
     * @todo public function findBy(array $criteria, $limit, $start = null, $end = null); //introduce in 3.0
     */

    /**
     * Reads data associated with the given token.
     *
     * The method returns false if the token does not exist in the storage.
     *
     * @param string $token A token
     *
     * @return Profile The profile associated with token
     */
    public function read($token);

    /**
     * Saves a Profile.
     *
     * @param Profile $profile A Profile instance.
     * @param array $indexes Collection of indexed values.
     *
     * @return bool Write operation successful
     *
     * @todo public function write(Profile $profile, array $indexes); // introduce in 3.0
     */

    /**
     * Purges all data from the database.
     */
    public function purge();
}
