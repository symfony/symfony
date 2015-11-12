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
 * AbstractProfilerStorage.
 *
 * @author Jelte Steijaert <jelte@khepri.be>
 */
abstract class AbstractProfilerStorage implements ProfilerStorageInterface
{
    /**
     * {@inheritdoc}
     */
    public function write(Profile $profile, array $indexes)
    {
        $res = $this->dowrite(
            $profile->getToken(),
            array_replace($indexes, array(
                'token' => $profile->getToken(),
                'parent_token' => $profile->getParentToken(),
                'time' => $profile->getTime(),
                'children' => base64_encode(serialize(array_map(function (Profile $p) { return $p->getToken(); }, $profile->getChildren()))),
                'data' => base64_encode(serialize($profile->getData())),
            )),
            array_replace($indexes, array('token' => $profile->getToken(), 'parent_token' => $profile->getParentToken(), 'time' => $profile->getTime()))
        );
        foreach ($profile->getChildren() as $childProfile) {
            $this->write($childProfile, $indexes);
        }

        return $res;
    }

    /**
     * {@inheritdoc}
     */
    public function read($token, Profile $parent = null)
    {
        $data = $this->doRead($token);

        if ( empty($data) ) {
            return;
        }

        $indexes = array_map(function($value, $key) {
            return !in_array($key, array('token', 'parent_token', 'time', 'children', 'data'))?$value:null;
        }, $data, array_keys($data));
        $indexes = array_combine(array_keys($data), $indexes);
        $indexes = array_filter($indexes, function($value) { return !is_null($value); });

        $profile = new Profile($token, $data['time'], $indexes);

        $profileData = unserialize(base64_decode($data['data']));
        $profile->setData($profileData);

        foreach (unserialize(base64_decode($data['children'])) as $childProfileToken) {
            $childProfile = $this->read($childProfileToken, $profile);
            $profile->addChild($childProfile);
        }

        if (isset($data['parent_token']) && null !== $data['parent_token'] && null === $parent) {
            $profile->setParent($this->read($data['parent_token']));
        }

        return $profile;
    }

    /**
     * Executes the actual write.
     *
     * @param $token
     * @param array $data
     * @param array $indexedData
     *
     * @return bool Write operation successful
     */
    abstract protected function doWrite($token, array $data, array $indexedData);

    /**
     * Executes the actual read.
     *
     * @param $token
     *
     * @return array
     */
    abstract protected function doRead($token);
}
