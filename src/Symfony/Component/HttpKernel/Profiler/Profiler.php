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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Symfony\Component\Profiler\HttpProfiler;
use Symfony\Component\Profiler\Storage\ProfilerStorageInterface;

/**
 * Profiler.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since 2.8, to be removed in 3.0. Use Symfony\Component\Profiler\HttpProfiler instead.
 */
class Profiler extends HttpProfiler
{
    /**
     * Constructor.
     *
     * @param ProfilerStorageInterface $storage A ProfilerStorageInterface instance
     * @param LoggerInterface          $logger  A LoggerInterface instance
     */
    public function __construct(ProfilerStorageInterface $storage, LoggerInterface $logger = null)
    {
        parent::__construct(new RequestStack(), $storage, $logger);
    }

    /**
     * Loads the Profile for the given Response.
     *
     * @param Response $response A Response instance
     *
     * @return Profile A Profile instance
     */
    public function loadProfileFromResponse(Response $response)
    {
        return $this->loadFromResponse($response);
    }

    /**
     * Loads the Profile for the given token.
     *
     * @param string $token A token
     *
     * @return Profile A Profile instance
     */
    public function loadProfile($token)
    {
        return $this->load($token);
    }

    /**
     * Saves a Profile.
     *
     * @param Profile $profile A Profile instance
     *
     * @return bool
     */
    public function saveProfile(Profile $profile)
    {
        foreach ( $profile->getCollectors() as $collector ) {
            if ($collector instanceof LateDataCollectorInterface) {
                $collector->lateCollect();
            }
        }
        return $this->save($profile);
    }

    /**
     * Collects data for the given Response.
     *
     * @param Request    $request   A Request instance
     * @param Response   $response  A Response instance
     * @param \Exception $exception An exception instance if the request threw one
     *
     * @return Profile|null A Profile instance or null if the profiler is disabled
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $this->requestStack->push($request);

        if ( $profile = parent::profile() ) {
            /** @var DataCollectorInterface $collector */
            foreach ($this->all() as $collector) {
                $collector->setToken($profile->getToken());
                if ($collector instanceof DataCollectorInterface) {
                    $collector->collect($request, $response, $exception);

                    // we need to clone for sub-requests
                    $profile->addCollector(clone $collector);
                }
            }
        }

        return $profile;
    }

    public function profile()
    {
        // Prevent the deprecation notice to be triggered all the time.
        // The onKernelRequest() method fires some logic only when the
        // RequestStack instance is not provided as a dependency.
        trigger_error('The ' . __METHOD__ . ' method should not be used till version 3.0 as it does not support 2.x DataCollectors. Use the method collect instead.', E_USER_DEPRECATED);

        return parent::profile();
    }
}
