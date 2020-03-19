<?php

namespace Symfony\Component\HttpKernel\Tests\Fixtures\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\ControllerConfiguration\Configuration\QueryParam;

/**
 * @QueryParam("foo")
 */
class AnnotatedController
{
    /**
     * @QueryParam("bar")
     */
    public function queryParamAction(string $foo, string $bar): Response
    {
        return new Response($foo.'.'.$bar);
    }
}
