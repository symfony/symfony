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
        $this->regexpList = array(
            0 => '{^(?'
                    .'|/(en|fr)/(?'
                        .'|admin/post/(?'
                            .'|(*:33)'
                            .'|new(*:43)'
                            .'|(\\d+)(*:55)'
                            .'|(\\d+)/edit(*:72)'
                            .'|(\\d+)/delete(*:91)'
                        .')'
                        .'|blog/(?'
                            .'|(*:107)'
                            .'|rss\\.xml(*:123)'
                            .'|p(?'
                                .'|age/([^/]++)(*:147)'
                                .'|osts/([^/]++)(*:168)'
                            .')'
                            .'|comments/(\\d+)/new(*:195)'
                            .'|search(*:209)'
                        .')'
                        .'|log(?'
                            .'|in(*:226)'
                            .'|out(*:237)'
                        .')'
                    .')'
                    .'|/(en|fr)?(*:256)'
                .')$}sD',
        );
        $this->dynamicRoutes = array(
            33 => array(array(array('_route' => 'a', '_locale' => 'en'), array('_locale'), null, null, null)),
            43 => array(array(array('_route' => 'b', '_locale' => 'en'), array('_locale'), null, null, null)),
            55 => array(array(array('_route' => 'c', '_locale' => 'en'), array('_locale', 'id'), null, null, null)),
            72 => array(array(array('_route' => 'd', '_locale' => 'en'), array('_locale', 'id'), null, null, null)),
            91 => array(array(array('_route' => 'e', '_locale' => 'en'), array('_locale', 'id'), null, null, null)),
            107 => array(array(array('_route' => 'f', '_locale' => 'en'), array('_locale'), null, null, null)),
            123 => array(array(array('_route' => 'g', '_locale' => 'en'), array('_locale'), null, null, null)),
            147 => array(array(array('_route' => 'h', '_locale' => 'en'), array('_locale', 'page'), null, null, null)),
            168 => array(array(array('_route' => 'i', '_locale' => 'en'), array('_locale', 'page'), null, null, null)),
            195 => array(array(array('_route' => 'j', '_locale' => 'en'), array('_locale', 'id'), null, null, null)),
            209 => array(array(array('_route' => 'k', '_locale' => 'en'), array('_locale'), null, null, null)),
            226 => array(array(array('_route' => 'l', '_locale' => 'en'), array('_locale'), null, null, null)),
            237 => array(array(array('_route' => 'm', '_locale' => 'en'), array('_locale'), null, null, null)),
            256 => array(array(array('_route' => 'n', '_locale' => 'en'), array('_locale'), null, null, null)),
        );
    }
}
