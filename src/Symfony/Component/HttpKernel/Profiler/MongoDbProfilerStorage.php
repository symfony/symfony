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
    private $mongo;

    /**
     * Constructor.
     *
     * @param string  $dsn        A data source name
     */
    public function __construct($dsn)
    {
        $this->dsn = $dsn;
    }

    /**
     * Finds profiler tokens for the given criteria.
     *
     * @param string $ip    The IP
     * @param string $url   The URL
     * @param string $limit The maximum number of tokens to return
     *
     * @return array An array of tokens
     */
    public function find($ip, $url, $limit)
    {
        $cursor = $this->getMongo()->find($this->buildQuery($ip, $url))->sort(array('time' => -1))->limit($limit);
        $return = array();
        foreach ($cursor as $profile) {
            $return[] = $profile['_id'];
        }

        return $return;
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
        $profile = $this->getMongo()->findOne(array('_id' => $token));

        return $profile !== null ? unserialize($profile['profile']) : null;
    }

    /**
     * Write data associated with the given token.
     *
     * @param Profile $profile A Profile instance
     *
     * @return Boolean Write operation successful
     */
    public function write(Profile $profile)
    {
        return $this->getMongo()->insert(array(
            '_id' => $profile->getToken(),
            'ip' => $profile->getIp(),
            'url' => $profile->getUrl() === null ? '' : $profile->getUrl(),
            'time' => $profile->getTime(),
            'profile' => serialize($profile)
        ));
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
                $mongo = new \Mongo($matches[1]);
                $database = $matches[2];
                $collection = $matches[3];
                $this->mongo = $mongo->selectCollection($database, $collection);
            } else {
                throw new \RuntimeException('Please check your configuration. You are trying to use MongoDB with an invalid dsn. "'.$this->dsn.'"');
            }
        }

        return $this->mongo;
    }

    /**
     * @param string $ip
     * @param string $url
     * @return array
     */
    private function buildQuery($ip, $url)
    {
        $query = array();

        if (!empty($ip)) {
            $query['ip'] = $ip;
        }

        if (!empty($url)) {
            $query['url'] = $url;
        }

        return $query;
    }
}
