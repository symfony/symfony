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
    public function getValue()
    {
        throw new \BadMethodCallException('A null result contains no value.');
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
    public function isHit()
    {
        return false;
    }
}
