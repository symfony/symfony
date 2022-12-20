<?php

use PHPUnit\Framework\TestCase;
use Rector\CodingStyle\Rector\MethodCall\PreferThisOrSelfMethodCallRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->parallel();
    $rectorConfig->paths([
        __DIR__.'/../src',
    ]);

    $rectorConfig->ruleWithConfiguration(PreferThisOrSelfMethodCallRector::class, [
        TestCase::class => 'prefer_self',
    ]);
};
