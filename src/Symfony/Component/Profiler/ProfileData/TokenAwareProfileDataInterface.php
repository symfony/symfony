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
 * TokenAwareProfileDataInterface.
 *
 * @author Jelte Steijaert <jelte@khepri.be>
 */
interface TokenAwareProfileDataInterface
{
    /**
     * Set the Token of the active profile.
     *
     * @param $token
     *
     * @api
     */
    public function setToken($token);
}