<?php

namespace Symfony\Component\Debug\Tests\Fixtures;

trait TraitWithAnnotatedParameters
{
    /**
     * `@param` annotations in traits are not parsed.
     */
    public function isSymfony()
    {
    }
}
