<?php

namespace Symfony\Component\ErrorHandler\Tests\Fixtures;

trait TraitWithAnnotatedParameters
{
    /**
     * `@param` annotations in traits are not parsed.
     */
    public function isSymfony()
    {
    }
}
