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
        $cursor = $this->getMongo()->find(array('ip' => $ip, 'url' => $url))->limit($limit);
        $return = array();
        foreach ($cursor as $profile) {
            $return[] = $profile['token'];
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
        $profile = $this->getMongo()->findOne(array('token' => $token));

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
            'token' => $profile->getToken(),
            'ip' => $profile->getIp(),
            'url' => $profile->getUrl() === null ? '' : $profile->getUrl(),
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
            $mongo = new \Mongo($this->dsn);
            list($database, $collection,) = explode('/', substr(parse_url($this->dsn, PHP_URL_PATH), 1));
            $this->mongo = $mongo->selectCollection($database, $collection);
        }
        
        return $this->mongo;
    }
}