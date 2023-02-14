<?php

use Rector\Config\RectorConfig;
use Rector\PHPUnit\Rector\Class_\StaticDataProviderClassMethodRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->parallel();
    $rectorConfig->paths([
        __DIR__.'/../src',
    ]);


    $rectorConfig->skip([
        __DIR__.'/../src/Symfony/Component/VarDumper/Tests/Fixtures/NotLoadableClass.php', // not loadable...
        __DIR__.'/../src/Symfony/Component/Config/Tests/Fixtures/ParseError.php', // not parseable...
        __DIR__.'/../src/Symfony/Bridge/ProxyManager/Tests/LazyProxy/PhpDumper/Fixtures/proxy-implem.php',
    ]);

    $rectorConfig->rule(StaticDataProviderClassMethodRector::class);
};
