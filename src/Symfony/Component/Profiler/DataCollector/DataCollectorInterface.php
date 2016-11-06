<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Profiler\DataCollector;

use Symfony\Component\Profiler\Data\DataInterface;
use Symfony\Component\Profiler\Profile;

/**
 * DataCollectorInterface.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface DataCollectorInterface
{
    /**
     * Collects data for the given Request and Response.
     *
     * @param DataInterface $data
     * @param Profile $profile
     * @return
     */
    public function collectData(DataInterface $data, Profile $profile);

    /**
     * Returns the name of the collector.
     *
     * @return string The collector name
     */
    public function getName();
}
