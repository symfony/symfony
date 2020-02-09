<?php

namespace Symfony\Bundle\FrameworkBundle\Routing\Loader\Configurator;

return function (RoutingConfigurator $routes) {
    $routes->add('classic_route', '/classic');

    $routes->add('template_route', '/static')
        ->template('static.html.twig', ['foo' => 'bar'])
        ->maxAge(300)
        ->sharedMaxAge(100)
        ->private()
        ->methods(['GET'])
        ->utf8()
        ->condition('abc')
    ;
    $routes->add('redirect_route', '/redirect')
        ->redirectToRoute('target_route')
        ->permanent()
        ->ignoreAttributes(['attr', 'ibutes'])
        ->keepRequestMethod()
        ->keepQueryParams()
        ->schemes(['http'])
        ->host('legacy')
        ->utf8()
    ;
    $routes->add('url_redirect_route', '/redirect-url')
        ->redirectToUrl('/url-target')
        ->permanent()
        ->scheme('http', 1)
        ->keepRequestMethod()
        ->host('legacy')
        ->utf8()
    ;
    $routes->add('not_a_route', '/not-a-path')
        ->gone()
        ->host('legacy')
        ->utf8()
    ;
    $routes->add('gone_route', '/gone-path')
        ->gone()
        ->permanent()
        ->utf8()
    ;
};
