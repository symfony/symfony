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
    public function queryParam(string $foo, string $bar): Response
    {
        return new Response($foo.'.'.$bar);
    }

    /**
     * @QueryParam("bar")
     */
    public function queryParamWithDefaultValues(string $foo = 'foo', string $bar = 'bar'): Response
    {
        return new Response($foo.'.'.$bar);
    }

    /**
     * @QueryParam("bar")
     */
    public function queryParamWithNullableValues(string $foo, ?string $bar): Response
    {
        return new Response($foo.'.'.$bar);
    }

    /**
     * @QueryParam("foo")
     */
    public function duplicatedQueryParamConfiguration(string $foo): void
    {
    }
}
