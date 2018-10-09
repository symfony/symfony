<?php

use Symfony\Component\Routing\Matcher\Dumper\PhpMatcherTrait;
use Symfony\Component\Routing\RequestContext;

/**
 * This class has been auto-generated
 * by the Symfony Routing Component.
 */
class ProjectUrlMatcher extends Symfony\Component\Routing\Tests\Fixtures\RedirectableUrlMatcher
{
    use PhpMatcherTrait;

    public function __construct(RequestContext $context)
    {
        $this->context = $context;
        $this->staticRoutes = array(
            '/trailing/simple/no-methods/' => array(array(array('_route' => 'simple_trailing_slash_no_methods'), null, null, null, null)),
            '/trailing/simple/get-method/' => array(array(array('_route' => 'simple_trailing_slash_GET_method'), null, array('GET' => 0), null, null)),
            '/trailing/simple/head-method/' => array(array(array('_route' => 'simple_trailing_slash_HEAD_method'), null, array('HEAD' => 0), null, null)),
            '/trailing/simple/post-method/' => array(array(array('_route' => 'simple_trailing_slash_POST_method'), null, array('POST' => 0), null, null)),
            '/not-trailing/simple/no-methods' => array(array(array('_route' => 'simple_not_trailing_slash_no_methods'), null, null, null, null)),
            '/not-trailing/simple/get-method' => array(array(array('_route' => 'simple_not_trailing_slash_GET_method'), null, array('GET' => 0), null, null)),
            '/not-trailing/simple/head-method' => array(array(array('_route' => 'simple_not_trailing_slash_HEAD_method'), null, array('HEAD' => 0), null, null)),
            '/not-trailing/simple/post-method' => array(array(array('_route' => 'simple_not_trailing_slash_POST_method'), null, array('POST' => 0), null, null)),
        );
        $this->regexpList = array(
            0 => '{^(?'
                    .'|/trailing/regex/(?'
                        .'|no\\-methods/([^/]++)/(*:47)'
                        .'|get\\-method/([^/]++)/(*:75)'
                        .'|head\\-method/([^/]++)/(*:104)'
                        .'|post\\-method/([^/]++)/(*:134)'
                    .')'
                    .'|/not\\-trailing/regex/(?'
                        .'|no\\-methods/([^/]++)(*:187)'
                        .'|get\\-method/([^/]++)(*:215)'
                        .'|head\\-method/([^/]++)(*:244)'
                        .'|post\\-method/([^/]++)(*:273)'
                    .')'
                .')$}sD',
        );
        $this->dynamicRoutes = array(
            47 => array(array(array('_route' => 'regex_trailing_slash_no_methods'), array('param'), null, null, null)),
            75 => array(array(array('_route' => 'regex_trailing_slash_GET_method'), array('param'), array('GET' => 0), null, null)),
            104 => array(array(array('_route' => 'regex_trailing_slash_HEAD_method'), array('param'), array('HEAD' => 0), null, null)),
            134 => array(array(array('_route' => 'regex_trailing_slash_POST_method'), array('param'), array('POST' => 0), null, null)),
            187 => array(array(array('_route' => 'regex_not_trailing_slash_no_methods'), array('param'), null, null, null)),
            215 => array(array(array('_route' => 'regex_not_trailing_slash_GET_method'), array('param'), array('GET' => 0), null, null)),
            244 => array(array(array('_route' => 'regex_not_trailing_slash_HEAD_method'), array('param'), array('HEAD' => 0), null, null)),
            273 => array(array(array('_route' => 'regex_not_trailing_slash_POST_method'), array('param'), array('POST' => 0), null, null)),
        );
    }
}
