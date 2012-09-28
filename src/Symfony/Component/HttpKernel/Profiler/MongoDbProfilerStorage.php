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

class MongoDbProfilerStorage implements ProfilerStorageInterface
{
    protected $dsn;
    protected $lifetime;
    private $mongo;

    /**
     * Constructor.
     *
     * @param string  $dsn      A data source name
     * @param string  $username Not used
     * @param string  $password Not used
     * @param integer $lifetime The lifetime to use for the purge
     */
    public function __construct($dsn, $username = '', $password = '', $lifetime = 86400)
    {
        $this->dsn = $dsn;
        $this->lifetime = (int) $lifetime;
    }

    /**
     * Finds profiler tokens for the given criteria.
     *
     * @param string $ip     The IP
     * @param string $url    The URL
     * @param string $limit  The maximum number of tokens to return
     * @param string $method The request method
     *
     * @return array An array of tokens
     */
    public function find($ip, $url, $limit, $method)
    {
        $cursor = $this->getMongo()->find($this->buildQuery($ip, $url, $method), array('_id', 'parent', 'ip', 'method', 'url', 'time'))->sort(array('time' => -1))->limit($limit);

        $tokens = array();
        foreach ($cursor as $profile) {
            $tokens[] = $this->getData($profile);
        }

        return $tokens;
    }

    /**
     * Purges all data from the database.
     */
    public function purge()
    {
        $this->getMongo()->remove(array());
    }

    /**
     * Reads data associated with the given token.
     *
     * The method returns false if the token does not exists in the storage.
     *
     * @param string $token A token
     *
     * @return Profile The profile associated with token
     */
    public function read($token)
    {
        $profile = $this->getMongo()->findOne(array('_id' => $token, 'data' => array('$exists' => true)));

        if (null !== $profile) {
            $profile = $this->createProfileFromData($this->getData($profile));
        }

        return $profile;
    }

    /**
     * Saves a Profile.
     *
     * @param Profile $profile A Profile instance
     *
     * @return Boolean Write operation successful
     */
    public function write(Profile $profile)
    {
        $this->cleanup();

        $record = array(
            '_id' => $profile->getToken(),
            'parent' => $profile->getParentToken(),
            'data' => base64_encode(serialize($profile->getCollectors())),
            'ip' => $profile->getIp(),
            'method' => $profile->getMethod(),
            'url' => $profile->getUrl(),
            'time' => $profile->getTime()
        );

        return $this->getMongo()->update(array('_id' => $profile->getToken()), array_filter($record, function ($v) { return !empty($v); }), array('upsert' => true));
    }

    /**
     * Internal convenience method that returns the instance of the MongoDB Collection
     *
     * @return \MongoCollection
     */
    protected function getMongo()
    {
        if ($this->mongo === null) {
            if (preg_match('#^(mongodb://.*)/(.*)/(.*)$#', $this->dsn, $matches)) {
                $mongo = new \Mongo($matches[1] . (!empty($matches[2]) ? '/' . $matches[2] : ''));
                $database = $matches[2];
                $collection = $matches[3];
                $this->mongo = $mongo->selectCollection($database, $collection);
            } else {
                throw new \RuntimeException(sprintf('Please check your configuration. You are trying to use MongoDB with an invalid dsn "%s". The expected format is "mongodb://user:pass@location/database/collection"', $this->dsn));
            }
        }

        return $this->mongo;
    }

    /**
     * @param array $data
     * @return Profile
     */
    protected function createProfileFromData(array $data)
    {
        $profile = $this->getProfile($data);

        if ($data['parent']) {
            $parent = $this->getMongo()->findOne(array('_id' => $data['parent'], 'data' => array('$exists' => true)));
            if ($parent) {
                $profile->setParent($this->getProfile($this->getData($parent)));
            }
        }

        $profile->setChildren($this->readChildren($data['token']));

        return $profile;
    }

    /**
     * @param string $token
     * @return array
     */
    protected function readChildren($token)
    {
        $profiles = array();

        $cursor = $this->getMongo()->find(array('parent' => $token, 'data' => array('$exists' => true)));
        foreach ($cursor as $d) {
            $profiles[] = $this->getProfile($this->getData($d));
        }

        return $profiles;
    }

    protected function cleanup()
    {
        $this->getMongo()->remove(array('time' => array('$lt' => time() - $this->lifetime)));
    }

    /**
     * @param string $ip
     * @param string $url
     * @param string $method
     * @return array
     */
    private function buildQuery($ip, $url, $method)
    {
        $query = array();

        if (!empty($ip)) {
            $query['ip'] = $ip;
        }

        if (!empty($url)) {
            $query['url'] = $url;
        }

        if (!empty($method)) {
            $query['method'] = $method;
        }

        return $query;
    }

    /**
     * @param array $data
     * @return array
     */
    private function getData(array $data)
    {
        return array(
            'token' => $data['_id'],
            'parent' => isset($data['parent']) ? $data['parent'] : null,
            'ip' => isset($data['ip']) ? $data['ip'] : null,
            'method' => isset($data['method']) ? $data['method'] : null,
            'url' => isset($data['url']) ? $data['url'] : null,
            'time' => isset($data['time']) ? $data['time'] : null,
            'data' => isset($data['data']) ? $data['data'] : null,
        );
    }

    /**
     * @param array $data
     * @return Profile
     */
    private function getProfile(array $data)
    {
        $profile = new Profile($data['token']);
        $profile->setIp($data['ip']);
        $profile->setMethod($data['method']);
        $profile->setUrl($data['url']);
        $profile->setTime($data['time']);
        $profile->setCollectors(unserialize(base64_decode($data['data'])));

        return $profile;
    }
}
