<?php
/**
 * Created by PhpStorm.
 * User: yosefderay
 * Date: 9/25/16
 * Time: 5:06 PM
 */

namespace Symfony\Component\Profiler\Context;

interface ContextInterface
{
    /**
     * @return \Exception|\Throwable|null
     */
    public function getException();

    /**
     * @return string
     */
    public function getName();

    /**
     * @return string
     */
    public function getType();

    /**
     * @return null|int
     */
    public function getStatusCode();
}
