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
            '/just_head' => array(array(array('_route' => 'just_head'), null, array('HEAD' => 0), null, false, null)),
            '/head_and_get' => array(array(array('_route' => 'head_and_get'), null, array('HEAD' => 0, 'GET' => 1), null, false, null)),
            '/get_and_head' => array(array(array('_route' => 'get_and_head'), null, array('GET' => 0, 'HEAD' => 1), null, false, null)),
            '/post_and_head' => array(array(array('_route' => 'post_and_head'), null, array('POST' => 0, 'HEAD' => 1), null, false, null)),
            '/put_and_post' => array(
                array(array('_route' => 'put_and_post'), null, array('PUT' => 0, 'POST' => 1), null, false, null),
                array(array('_route' => 'put_and_get_and_head'), null, array('PUT' => 0, 'GET' => 1, 'HEAD' => 2), null, false, null),
            ),
        );
    }
}
