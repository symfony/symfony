<?php

namespace Symfony\Component\Cache\Data;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class FreshItem extends ValidItem
{
    /**
     * {@inheritdoc}
     */
    public function isCached()
    {
        return false;
    }
}
