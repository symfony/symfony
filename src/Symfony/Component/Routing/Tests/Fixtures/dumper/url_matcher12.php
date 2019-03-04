<?php

use Symfony\Component\Routing\Matcher\Dumper\CompiledUrlMatcherTrait;
use Symfony\Component\Routing\RequestContext;

/**
 * This class has been auto-generated
 * by the Symfony Routing Component.
 */
class ProjectUrlMatcher extends Symfony\Component\Routing\Matcher\UrlMatcher
{
    use CompiledUrlMatcherTrait;

    public function __construct(RequestContext $context)
    {
        $this->context = $context;
        $this->regexpList = [
            0 => '{^(?'
                    .'|/abc([^/]++)/(?'
                        .'|1(?'
                            .'|(*:27)'
                            .'|0(?'
                                .'|(*:38)'
                                .'|0(*:46)'
                            .')'
                        .')'
                        .'|2(?'
                            .'|(*:59)'
                            .'|0(?'
                                .'|(*:70)'
                                .'|0(*:78)'
                            .')'
                        .')'
                    .')'
                .')/?$}sD',
        ];
        $this->dynamicRoutes = [
            27 => [[['_route' => 'r1'], ['foo'], null, null, false, false, null]],
            38 => [[['_route' => 'r10'], ['foo'], null, null, false, false, null]],
            46 => [[['_route' => 'r100'], ['foo'], null, null, false, false, null]],
            59 => [[['_route' => 'r2'], ['foo'], null, null, false, false, null]],
            70 => [[['_route' => 'r20'], ['foo'], null, null, false, false, null]],
            78 => [
                [['_route' => 'r200'], ['foo'], null, null, false, false, null],
                [null, null, null, null, false, false, 0],
            ],
        ];
    }
}
