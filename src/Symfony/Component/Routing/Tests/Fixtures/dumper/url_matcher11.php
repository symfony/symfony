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
                        .'|admin/post(?'
                            .'|(*:32)'
                            .'|/(?'
                                .'|new(*:46)'
                                .'|(\\d+)(*:58)'
                                .'|(\\d+)/edit(*:75)'
                                .'|(\\d+)/delete(*:94)'
                            .')'
                        .')'
                        .'|blog(?'
                            .'|(*:110)'
                            .'|/(?'
                                .'|rss\\.xml(*:130)'
                                .'|p(?'
                                    .'|age/([^/]++)(*:154)'
                                    .'|osts/([^/]++)(*:175)'
                                .')'
                                .'|comments/(\\d+)/new(*:202)'
                                .'|search(*:216)'
                            .')'
                        .')'
                        .'|log(?'
                            .'|in(*:234)'
                            .'|out(*:245)'
                        .')'
                    .')'
                    .'|/(en|fr)?(*:264)'
                .')(?:/?)$}sD',
        );
        $this->dynamicRoutes = array(
            32 => array(array(array('_route' => 'a', '_locale' => 'en'), array('_locale'), null, null, true, null)),
            46 => array(array(array('_route' => 'b', '_locale' => 'en'), array('_locale'), null, null, false, null)),
            58 => array(array(array('_route' => 'c', '_locale' => 'en'), array('_locale', 'id'), null, null, false, null)),
            75 => array(array(array('_route' => 'd', '_locale' => 'en'), array('_locale', 'id'), null, null, false, null)),
            94 => array(array(array('_route' => 'e', '_locale' => 'en'), array('_locale', 'id'), null, null, false, null)),
            110 => array(array(array('_route' => 'f', '_locale' => 'en'), array('_locale'), null, null, true, null)),
            130 => array(array(array('_route' => 'g', '_locale' => 'en'), array('_locale'), null, null, false, null)),
            154 => array(array(array('_route' => 'h', '_locale' => 'en'), array('_locale', 'page'), null, null, false, null)),
            175 => array(array(array('_route' => 'i', '_locale' => 'en'), array('_locale', 'page'), null, null, false, null)),
            202 => array(array(array('_route' => 'j', '_locale' => 'en'), array('_locale', 'id'), null, null, false, null)),
            216 => array(array(array('_route' => 'k', '_locale' => 'en'), array('_locale'), null, null, false, null)),
            234 => array(array(array('_route' => 'l', '_locale' => 'en'), array('_locale'), null, null, false, null)),
            245 => array(array(array('_route' => 'm', '_locale' => 'en'), array('_locale'), null, null, false, null)),
            264 => array(array(array('_route' => 'n', '_locale' => 'en'), array('_locale'), null, null, false, null)),
        );
    }
}
