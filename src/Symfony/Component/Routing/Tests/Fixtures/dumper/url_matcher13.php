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
        $this->matchHost = true;
        $this->regexpList = array(
            0 => '{^(?'
                .'|(?i:([^\\.]++)\\.exampple\\.com)\\.(?'
                    .'|/abc([^/]++)(?'
                        .'|(*:56)'
                    .')'
                .')'
                .')(?:/?)$}sD',
        );
        $this->dynamicRoutes = array(
            56 => array(
                array(array('_route' => 'r1'), array('foo', 'foo'), null, null, false, null),
                array(array('_route' => 'r2'), array('foo', 'foo'), null, null, false, null),
            ),
        );
    }
}
