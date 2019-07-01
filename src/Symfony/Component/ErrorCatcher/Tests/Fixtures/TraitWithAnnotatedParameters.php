<?php

namespace Symfony\Component\ErrorCatcher\Tests\Fixtures;

trait TraitWithAnnotatedParameters
{
    /**
     * `@param` annotations in traits are not parsed.
     */
    public function isSymfony()
    {
    }
}
