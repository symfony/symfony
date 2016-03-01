<?php

namespace Symfony\Component\HttpKernel\Tests\Fixtures\Controller;

class VariadicController
{
    public function action($foo, ...$bar)
    {
    }
}
