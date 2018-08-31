<?php

namespace Symfony\Component\Debug\Tests\Fixtures;

/**
 * Ensures a deprecation is triggered when a new parameter is not declared in child classes.
 */
interface InterfaceWithAnnotatedParameters
{
    /**
     * @param bool $matrix
     */
    public function whereAmI();
}
