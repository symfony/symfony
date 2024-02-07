<?php

$files = [
    __DIR__.'/../src/Symfony/Bridge/Twig/composer.json',
    __DIR__.'/../src/Symfony/Bundle/FrameworkBundle/composer.json',
    __DIR__.'/../src/Symfony/Bundle/SecurityBundle/composer.json',
    __DIR__.'/../src/Symfony/Bundle/TwigBundle/composer.json',
    __DIR__.'/../src/Symfony/Bundle/WebProfilerBundle/composer.json',
    __DIR__.'/../src/Symfony/Component/HttpKernel/composer.json',
    __DIR__.'/../src/Symfony/Component/VarDumper/composer.json',
];

foreach ($files as $file) {
    $contents = json_decode(file_get_contents($file), true);

    if (!isset($contents['require']['twig/twig']) && !isset($contents['require-dev']['twig/twig'])) {
        continue;
    }

    if (isset($contents['conflict']['twig/twig'])) {
        $contents['conflict']['twig/twig'] .= '|>3.8.0';
    } else {
        $contents['conflict']['twig/twig'] = '>3.8.0';
    }

    file_put_contents($file, json_encode($contents));
}
