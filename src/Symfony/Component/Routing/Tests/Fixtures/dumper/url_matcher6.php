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
        $this->staticRoutes = array(
            '/trailing/simple/no-methods' => array(array(array('_route' => 'simple_trailing_slash_no_methods'), null, null, null, true, null)),
            '/trailing/simple/get-method' => array(array(array('_route' => 'simple_trailing_slash_GET_method'), null, array('GET' => 0), null, true, null)),
            '/trailing/simple/head-method' => array(array(array('_route' => 'simple_trailing_slash_HEAD_method'), null, array('HEAD' => 0), null, true, null)),
            '/trailing/simple/post-method' => array(array(array('_route' => 'simple_trailing_slash_POST_method'), null, array('POST' => 0), null, true, null)),
            '/not-trailing/simple/no-methods' => array(array(array('_route' => 'simple_not_trailing_slash_no_methods'), null, null, null, false, null)),
            '/not-trailing/simple/get-method' => array(array(array('_route' => 'simple_not_trailing_slash_GET_method'), null, array('GET' => 0), null, false, null)),
            '/not-trailing/simple/head-method' => array(array(array('_route' => 'simple_not_trailing_slash_HEAD_method'), null, array('HEAD' => 0), null, false, null)),
            '/not-trailing/simple/post-method' => array(array(array('_route' => 'simple_not_trailing_slash_POST_method'), null, array('POST' => 0), null, false, null)),
        );
        $this->regexpList = array(
            0 => '{^(?'
                    .'|/trailing/regex/(?'
                        .'|no\\-methods/([^/]++)(*:46)'
                        .'|get\\-method/([^/]++)(*:73)'
                        .'|head\\-method/([^/]++)(*:101)'
                        .'|post\\-method/([^/]++)(*:130)'
                    .')'
                    .'|/not\\-trailing/regex/(?'
                        .'|no\\-methods/([^/]++)(*:183)'
                        .'|get\\-method/([^/]++)(*:211)'
                        .'|head\\-method/([^/]++)(*:240)'
                        .'|post\\-method/([^/]++)(*:269)'
                    .')'
                .')(?:/?)$}sD',
        );
        $this->dynamicRoutes = array(
            46 => array(array(array('_route' => 'regex_trailing_slash_no_methods'), array('param'), null, null, true, null)),
            73 => array(array(array('_route' => 'regex_trailing_slash_GET_method'), array('param'), array('GET' => 0), null, true, null)),
            101 => array(array(array('_route' => 'regex_trailing_slash_HEAD_method'), array('param'), array('HEAD' => 0), null, true, null)),
            130 => array(array(array('_route' => 'regex_trailing_slash_POST_method'), array('param'), array('POST' => 0), null, true, null)),
            183 => array(array(array('_route' => 'regex_not_trailing_slash_no_methods'), array('param'), null, null, false, null)),
            211 => array(array(array('_route' => 'regex_not_trailing_slash_GET_method'), array('param'), array('GET' => 0), null, false, null)),
            240 => array(array(array('_route' => 'regex_not_trailing_slash_HEAD_method'), array('param'), array('HEAD' => 0), null, false, null)),
            269 => array(array(array('_route' => 'regex_not_trailing_slash_POST_method'), array('param'), array('POST' => 0), null, false, null)),
        );
    }
}
