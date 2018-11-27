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
        $this->staticRoutes = array(
            '/' => array(
                array(array('_route' => 'a'), '#^(?P<d>[^\\.]++)\\.e\\.c\\.b\\.a$#sDi', null, null, false, null),
                array(array('_route' => 'c'), '#^(?P<e>[^\\.]++)\\.e\\.c\\.b\\.a$#sDi', null, null, false, null),
                array(array('_route' => 'b'), 'd.c.b.a', null, null, false, null),
            ),
        );
    }
}
