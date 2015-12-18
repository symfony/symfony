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

use Symfony\Component\Profiler\ProfileData\ProfileDataInterface;

/**
 * DataCollectorInterface.
 *
 * @author Jelte Steijaert <jelte@khepri.be>
 */
interface DataCollectorInterface
{
    /**
     * Returns the collected data.
     *
     * @return ProfileDataInterface
     *
     * @todo public function getCollectedData(); //introduce in 3.0
     */
}
