<?php

namespace Symfony\Component\Cache\Data;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
interface DataInterface
{
    /**
     * @return boolean
     */
    public function isValid();

    /**
     * @return boolean
     */
    public function isCached();

    /**
     * @return boolean
     */
    public function isCollection();
}
