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
    public function get()
    {
        throw new \LogicException('A null result contains no data.');
    }

    /**
     * {@inheritdoc}
     */
    public function getKey()
    {
        throw new \LogicException('A null result has no key.');
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
