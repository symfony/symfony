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

@trigger_error('The '.__NAMESPACE__.'\Profiler class is deprecated since Symfony 2.8 and will be removed in 3.0. Use Symfony\Component\Profiler\Profiler instead.', E_USER_DEPRECATED);

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Symfony\Component\Profiler\DataCollector\LateDataCollectorInterface as BaseLateDataCollectorInterface;
use Symfony\Component\Profiler\ProfileData\GenericProfileData;
use Symfony\Component\Profiler\Profiler as BaseProfiler;

/**
 * Profiler.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated Deprecated since Symfony 2.8, to be removed in Symfony 3.0.
 *             Use {@link Symfony\Component\Profiler\Profiler} instead.
 */
class Profiler extends BaseProfiler
{
    /**
     * Loads the Profile for the given Response.
     *
     * @param Response $response A Response instance
     *
     * @return Profile A Profile instance
     */
    public function loadProfileFromResponse(Response $response)
    {
        if (!$token = $response->headers->get('X-Debug-Token')) {
            return false;
        }
        
        return $this->loadProfile($token);
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
        return $this->storage->read($token);
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
        foreach ($this->collectors as $collector) {
            if ($collector instanceof LateDataCollectorInterface) {
                $collector->lateCollect();
            }
            if ($collector instanceof BaseLateDataCollectorInterface) {
                if ( !method_exists($collector, 'getCollectedData') ) {
                    $profile->add(new GenericProfileData($collector));
                } else {
                    $profile->add($collector->getCollectedData());
                }
            }
        }

        if (!($ret = $this->storage->write($profile, $profile->getIndexes())) && null !== $this->logger) {
            $this->logger->warning('Unable to store the profiler information.', array('configured_storage' => get_class($this->storage)));
        }

        return $ret;
    }

    /**
     * Collects data for the given Response.
     *
     * @param Request $request A Request instance
     * @param Response $response A Response instance
     * @param \Exception $exception An exception instance if the request threw one
     *
     * @return Profile|null A Profile instance or null if the profiler is disabled
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        if (!$this->enabled) {
            return;
        }

        $profile = new Profile(substr(hash('sha256', uniqid(mt_rand(), true)), 0, 6));
        $profile->setTime(time());
        $profile->setUrl($request->getUri());
        $profile->setIp($request->getClientIp());
        $profile->setMethod($request->getMethod());
        $profile->setStatusCode($response->getStatusCode());

        $response->headers->set('X-Debug-Token', $profile->getToken());

        foreach ($this->collectors as $collector) {
            if ( $collector instanceof DataCollectorInterface ) {
                $collector->collect($request, $response, $exception);
            }
            if (!($collector instanceof \Symfony\Component\Profiler\DataCollector\LateDataCollectorInterface)) {
                if ( !method_exists($collector, 'getCollectedData') ) {
                    $profile->add(new GenericProfileData($collector));
                } else {
                    $profile->add($collector->getCollectedData());
                }
            }
        }

        return $profile;
    }

    /**
     * Finds profiler tokens for the given criteria.
     *
     * @param string $ip     The IP
     * @param string $url    The URL
     * @param string $limit  The maximum number of tokens to return
     * @param string $method The request method
     * @param string $start  The start date to search from
     * @param string $end    The end date to search to
     *
     * @return array An array of tokens
     *
     * @see http://php.net/manual/en/datetime.formats.php for the supported date/time formats
     */
    public function find($ip, $url, $limit, $method, $start, $end)
    {
        return $this->storage->find($ip, $url, $limit, $method, $this->getTimestamp($start), $this->getTimestamp($end));
    }

    /**
     * Purges all data from the storage.
     */
    public function purge()
    {
        $this->storage->purge();
    }
    /**
     * Exports the current profiler data.
     *
     * @param Profile $profile A Profile instance
     *
     * @return string The exported data
     *     
     * @deprecated since Symfony 2.8, to be removed in 3.0.
     */
    public function export(Profile $profile)
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since version 2.8 and will be removed in 3.0.', E_USER_DEPRECATED);

        return base64_encode(serialize($profile));
    }
    /**
     * Imports data into the profiler storage.
     *
     * @param string $data A data string as exported by the export() method
     *
     * @return Profile A Profile instance
     *
     * @deprecated since Symfony 2.8, to be removed in 3.0.
     */
    public function import($data)
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since version 2.8 and will be removed in 3.0.', E_USER_DEPRECATED);
        
        $profile = unserialize(base64_decode($data));
        
        if ($this->storage->read($profile->getToken())) {
            return false;
        }
        
        $this->saveProfile($profile);
        
        return $profile;
    }

    private function getTimestamp($value)
    {
        if (null === $value || '' == $value) {
            return;
        }
        try {
            $value = new \DateTime(is_numeric($value) ? '@'.$value : $value);
        } catch (\Exception $e) {
            return;
        }
        return $value->getTimestamp();
    }
}
