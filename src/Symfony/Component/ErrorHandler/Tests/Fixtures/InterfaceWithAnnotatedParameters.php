<?php

namespace Symfony\Component\ErrorHandler\Tests\Fixtures;

/**
 * Ensures a deprecation is triggered when a new parameter is not declared in child classes.
 */
interface InterfaceWithAnnotatedParameters
{
    /**
     * @param bool $matrix
     */
    public function whereAmI();

    /**
     * @param       $noType
     * @param callable(\Throwable|null $reason, mixed $value) $callback and a comment
     * about this great param
     * @param string                                          $param (comment with $dollar)
     * @param $defined
     * @param  callable  ($a,  $b)  $anotherOne
     * @param callable (mixed $a, $b) $definedCallable
     * @param Type$WithDollarIsStillAType $ccc
     * @param \JustAType
     */
    public function iAmHere();
}
