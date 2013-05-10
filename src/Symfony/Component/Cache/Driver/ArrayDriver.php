<?php

namespace Symfony\Component\Cache\Driver;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class ArrayDriver extends AbstractDriver
{
    /**
     * @var array
     */
    private $data = array();

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        $this->data = array();
    }

    /**
     * {@inheritdoc}
     */
    protected function fetchOne($key)
    {
        if (!isset($this->data[$key])) {
            return array();
        }

        return array($key => $this->data[$key]);
    }

    /**
     * {@inheritdoc}
     */
    protected function fetchMany(array $keys)
    {
        $result = array();
        foreach ($keys as $key) {
            if (isset($this->data[$key])) {
                $result[$key] = $this->data[$key];
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function storeOne($key, $data)
    {
        $this->data[$key] = $data;

        return array($key);
    }

    /**
     * {@inheritdoc}
     */
    protected function storeMany(array $data)
    {
        $this->data = array_merge($this->data, $data);

        return array_keys($data);
    }

    /**
     * {@inheritdoc}
     */
    protected function deleteOne($key)
    {
        if (isset($this->data[$key])) {
            unset($this->data[$key]);

            return array($key);
        }

        return array();
    }

    /**
     * {@inheritdoc}
     */
    protected function deleteMany(array $keys)
    {
        $deleted = array();
        foreach ($keys as $key) {
            if (isset($this->data[$key])) {
                unset($this->data[$key]);
                $deleted[] = $key;
            }
        }

        return $deleted;
    }
}
