<?php

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->add('faq', '/faq.html')
        ->template('pages/faq.html.twig')
        ->defaults(['maxAge' => 300]);

    $routes->add('doc_debug_mode', '/doc/{version}/debug-mode')
        ->redirectToRoute('doc_page')
        ->defaults(['page' => 'configuration/front_controllers_and_kernel', '_fragment' => 'debug-mode']);

    $routes->add('doc_index', '/doc/{version}')
        ->redirectTo('/doc/current/{page}');

    $routes->add('external', '/external')
        ->redirectTo('https://example.com/some/path');

    $routes->collection('collection_')
        ->template('pages/faq.html.twig')
        ->defaults(['maxAge' => 300])
        ->add('faq', '/collection/faq.html');

    $routes->add('localized_faq', ['en' => '/en/faq', 'fr' => '/fr/faq'])
        ->template('pages/faq.html.twig');

    $routes->import('../defaults.php')
        ->redirectTo('/faq.html')
        ->defaults(['permanent' => true])
        ->namePrefix('imported_');
};
