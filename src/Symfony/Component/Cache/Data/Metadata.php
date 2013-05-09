<?php

namespace Symfony\Component\Cache\Data;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class Metadata
{
    /**
     * @var array
     */
    private $data;

    /**
     * @param array $data
     */
    public function __construct(array $data = array())
    {
        $this->data = $data;
    }

    /**
     * @param string $key
     *
     * @return boolean
     */
    public function has($key)
    {
        return isset($this->data[$key]);
    }

    /**
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return isset($this->data[$key]) ? $this->data[$key] : $default;
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return Metadata
     */
    public function set($key, $value = null)
    {
        $this->data[$key] = $value;

        return $this;
    }

    /**
     * @param array $data
     *
     * @return Metadata
     */
    public function merge(array $data)
    {
        $this->data = array_merge($this->data, $data);

        return $this;
    }

    /**
     * @return array
     */
    public function all()
    {
        return $this->data;
    }
}
