<?php

namespace Symfony\Component\Cache\Data;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
interface ItemInterface extends DataInterface
{
    /**
     * @return string
     */
    public function getKey();

    /**
     * @return mixed
     */
    public function getData();
}
