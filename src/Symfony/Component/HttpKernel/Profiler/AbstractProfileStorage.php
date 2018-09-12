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

abstract class AbstractProfileStorage implements ProfilerStorageInterface
{
    /**
     * {@inheritdoc}
     */
    public function read($token)
    {
        if (!$token) {
            return false;
        }

        $data = $this->readProfileData($token);
        if (!$data) {
            return false;
        }

        return $this->createProfileFromData($token, $data);
    }

    abstract public function readProfileData($token);

    /**
     * Create a profile from serializable data.
     *
     * @param string $token
     * @param array  $data
     * @param string $parent
     *
     * @return Profile
     */
    protected function createProfileFromData($token, $data, $parent = null)
    {
        $profile = new Profile($token);
        $profile->setIp($data['ip']);
        $profile->setMethod($data['method']);
        $profile->setUrl($data['url']);
        $profile->setTime($data['time']);
        $profile->setStatusCode($data['status_code']);
        $profile->setCollectors($data['data']);

        if (!$parent && $data['parent']) {
            $parent = $this->read($data['parent']);
        }

        if ($parent) {
            $profile->setParent($parent);
        }

        foreach ($data['children'] as $childToken) {
            $childProfileData = $this->readProfileData($childToken);
            if (!$childProfileData) {
                continue;
            }
            $profile->addChild($this->createProfileFromData($childToken, $childProfileData, $profile));
        }

        return $profile;
    }

    /**
     * Creates a serializable version of the profile.
     *
     * @param Profile $profile
     *
     * @return array
     */
    protected function getProfileData(Profile $profile)
    {
        $profileToken = $profile->getToken();
        // when there are errors in sub-requests, the parent and/or children tokens
        // may equal the profile token, resulting in infinite loops
        $parentToken = $profile->getParentToken() !== $profileToken ? $profile->getParentToken() : null;
        $childrenToken = array_filter(
            array_map(
                function (Profile $p) use ($profileToken) {
                    return $profileToken !== $p->getToken() ? $p->getToken() : null;
                },
                $profile->getChildren()
            )
        );

        // Store profile
        $data = array(
            'token' => $profileToken,
            'parent' => $parentToken,
            'children' => $childrenToken,
            'data' => $profile->getCollectors(),
            'ip' => $profile->getIp(),
            'method' => $profile->getMethod(),
            'url' => $profile->getUrl(),
            'time' => $profile->getTime(),
            'status_code' => $profile->getStatusCode(),
        );

        return $data;
    }

    /**
     * Creates an array for storing in the index.
     *
     * @return array
     */
    protected function getProfileIndexItem(Profile $profile)
    {
        return array(
            $profile->getToken(),
            $profile->getIp(),
            $profile->getMethod(),
            $profile->getUrl(),
            $profile->getTime(),
            $profile->getParentToken(),
            $profile->getStatusCode(),
        );
    }

    /**
     * Checks if the index item matches the filters.
     */
    protected function checkIndexItem($indexItem, $ip, $url, $method, $start, $end, $statusCode)
    {
        list($csvToken, $csvIp, $csvMethod, $csvUrl, $csvTime, $csvParent, $csvStatusCode) = $indexItem;
        $csvTime = (int) $csvTime;

        if ($ip && false === strpos($csvIp, $ip) || $url && false === strpos($csvUrl, $url) || $method && false === strpos($csvMethod, $method) || $statusCode && false === strpos($csvStatusCode, $statusCode)) {
            return;
        }

        if (!empty($start) && $csvTime < $start) {
            return;
        }

        if (!empty($end) && $csvTime > $end) {
            return;
        }

        return array(
            'token' => $csvToken,
            'ip' => $csvIp,
            'method' => $csvMethod,
            'url' => $csvUrl,
            'time' => $csvTime,
            'parent' => $csvParent,
            'status_code' => $csvStatusCode,
        );
    }
}
