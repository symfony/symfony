<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Profiler\ProfileData;

/**
 * Interface ProfileDataInterface
 * @package Symfony\Component\Profiler\ProfileData
 *
 * @author Jelte Steijaert <jelte@khepri.be>
 */
interface ProfileDataInterface
{
    public function getName();
}