<?php

use Symfony\Component\Routing\Loader\Configurator\Routes;

return Routes::config([
    'faq' => ['path' => '/faq.html', 'template' => 'pages/faq.html.twig'],
    'doc_debug_mode' => ['path' => '/doc/{version}/debug-mode', 'redirect_to_route' => 'doc_page'],
    'external' => ['path' => '/external', 'redirect_to' => 'https://example.com/some/path'],
]);
