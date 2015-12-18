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
 * ProfileDataInterface.
 *
 * @author Jelte Steijaert <jelte@khepri.be>
 */
interface ProfileDataInterface extends \Serializable
{
    public function getName();
}
