<?php

namespace Symfony\Component\Cache\Data;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class NullResult implements ItemInterface
{
    /**
     * {@inheritdoc}
     */
    public function getData()
    {
        throw new \BadMethodCallException('A null result contains no data.');
    }

    /**
     * {@inheritdoc}
     */
    public function getKey()
    {
        throw new \BadMethodCallException('A null result has no key.');
    }

    /**
     * {@inheritdoc}
     */
    public function isValid()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isCached()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isCollection()
    {
        return false;
    }
}
