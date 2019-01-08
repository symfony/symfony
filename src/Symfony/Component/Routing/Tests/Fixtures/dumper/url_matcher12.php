<?php

use Symfony\Component\Routing\Matcher\Dumper\PhpMatcherTrait;
use Symfony\Component\Routing\RequestContext;

/**
 * This class has been auto-generated
 * by the Symfony Routing Component.
 */
class ProjectUrlMatcher extends Symfony\Component\Routing\Matcher\UrlMatcher
{
    use PhpMatcherTrait;

    public function __construct(RequestContext $context)
    {
        $this->context = $context;
        $this->regexpList = array(
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
        );
        $this->dynamicRoutes = array(
            27 => array(array(array('_route' => 'r1'), array('foo'), null, null, false, false, null)),
            38 => array(array(array('_route' => 'r10'), array('foo'), null, null, false, false, null)),
            46 => array(array(array('_route' => 'r100'), array('foo'), null, null, false, false, null)),
            59 => array(array(array('_route' => 'r2'), array('foo'), null, null, false, false, null)),
            70 => array(array(array('_route' => 'r20'), array('foo'), null, null, false, false, null)),
            78 => array(array(array('_route' => 'r200'), array('foo'), null, null, false, false, null)),
        );
    }
}
