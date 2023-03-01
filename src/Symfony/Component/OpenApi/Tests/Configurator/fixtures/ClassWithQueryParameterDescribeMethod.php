<?php

namespace Symfony\Component\OpenApi\Tests\Configurator\fixtures;

use Symfony\Component\OpenApi\Builder\OpenApiBuilderInterface;
use Symfony\Component\OpenApi\Configurator\QueryParametersConfigurator;

class ClassWithQueryParameterDescribeMethod
{
    public static function describeQueryParameters(QueryParametersConfigurator $configurator, OpenApiBuilderInterface $openApi): void
    {
        $configurator
            ->queryParameter($openApi->queryParameter('test'))
        ;
    }
}
