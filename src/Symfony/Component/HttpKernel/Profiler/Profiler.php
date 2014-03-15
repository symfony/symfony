<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Profiler;

use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated Deprecated in 2.5, to be removed in 3.0. Use the HttpProfiler component instead.
 */
class Profiler extends \Symfony\Component\HttpProfiler\Profiler
{
    /**
     * This method has been renamed to loadFromResponse() in HttpProfiler.
     *
     * @deprecated Deprecated in 2.5, to be removed in 3.0. Use loadFromResponse() instead.
     */
    public function loadProfileFromResponse(Response $response)
    {
        return $this->loadFromResponse($response);
    }

    /**
     * This method has been renamed to load() in HttpProfiler.
     *
     * @deprecated Deprecated in 2.5, to be removed in 3.0. Use load() instead.
     */
    public function loadProfile($token)
    {
        return $this->load($token);
    }

    /**
     * This method has been renamed to save() in HttpProfiler.
     *
     * @deprecated Deprecated in 2.5, to be removed in 3.0. Use save() instead.
     */
    public function saveProfile(Profile $profile)
    {
        return $this->save($profile);
    }
}
