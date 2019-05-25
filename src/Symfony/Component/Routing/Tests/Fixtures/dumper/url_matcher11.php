<?php

use Symfony\Component\Routing\Matcher\Dumper\CompiledUrlMatcherTrait;
use Symfony\Component\Routing\RequestContext;

/**
 * This class has been auto-generated
 * by the Symfony Routing Component.
 */
class ProjectUrlMatcher extends Symfony\Component\Routing\Tests\Fixtures\RedirectableUrlMatcher
{
    use CompiledUrlMatcherTrait;

    public function __construct(RequestContext $context)
    {
        $this->context = $context;
        $this->regexpList = [
            0 => '{^(?'
                    .'|(?:/(en|fr))?/(?'
                        .'|admin/post(?'
                            .'|(*:37)'
                            .'|/(?'
                                .'|new(*:51)'
                                .'|(\\d+)(*:63)'
                                .'|(\\d+)/edit(*:80)'
                                .'|(\\d+)/delete(*:99)'
                            .')'
                        .')'
                        .'|blog(?'
                            .'|(*:115)'
                            .'|/(?'
                                .'|rss\\.xml(*:135)'
                                .'|p(?'
                                    .'|age/([^/]++)(*:159)'
                                    .'|osts/([^/]++)(*:180)'
                                .')'
                                .'|comments/(\\d+)/new(*:207)'
                                .'|search(*:221)'
                            .')'
                        .')'
                        .'|log(?'
                            .'|in(*:239)'
                            .'|out(*:250)'
                        .')'
                    .')'
                    .'|/(en|fr)?(*:269)'
                .')/?$}sD',
        ];
        $this->dynamicRoutes = [
            37 => [[['_route' => 'a', '_locale' => 'en'], ['_locale'], null, null, true, false, null]],
            51 => [[['_route' => 'b', '_locale' => 'en'], ['_locale'], null, null, false, false, null]],
            63 => [[['_route' => 'c', '_locale' => 'en'], ['_locale', 'id'], null, null, false, true, null]],
            80 => [[['_route' => 'd', '_locale' => 'en'], ['_locale', 'id'], null, null, false, false, null]],
            99 => [[['_route' => 'e', '_locale' => 'en'], ['_locale', 'id'], null, null, false, false, null]],
            115 => [[['_route' => 'f', '_locale' => 'en'], ['_locale'], null, null, true, false, null]],
            135 => [[['_route' => 'g', '_locale' => 'en'], ['_locale'], null, null, false, false, null]],
            159 => [[['_route' => 'h', '_locale' => 'en'], ['_locale', 'page'], null, null, false, true, null]],
            180 => [[['_route' => 'i', '_locale' => 'en'], ['_locale', 'page'], null, null, false, true, null]],
            207 => [[['_route' => 'j', '_locale' => 'en'], ['_locale', 'id'], null, null, false, false, null]],
            221 => [[['_route' => 'k', '_locale' => 'en'], ['_locale'], null, null, false, false, null]],
            239 => [[['_route' => 'l', '_locale' => 'en'], ['_locale'], null, null, false, false, null]],
            250 => [[['_route' => 'm', '_locale' => 'en'], ['_locale'], null, null, false, false, null]],
            269 => [
                [['_route' => 'n', '_locale' => 'en'], ['_locale'], null, null, false, true, null],
                [null, null, null, null, false, false, 0],
            ],
        ];
    }
}
