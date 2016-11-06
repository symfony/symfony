<?php
/**
 * Created by PhpStorm.
 * User: yosefderay
 * Date: 9/25/16
 * Time: 5:06 PM
 */

namespace Symfony\Component\Profiler\Data;

interface DataInterface
{
    /**
     * @return \Exception|\Throwable|null
     */
    public function getException();

    /**
     * @return null|string
     */
    public function getUri();

    /**
     * @return null|string
     */
    public function getStatusCode();
}
