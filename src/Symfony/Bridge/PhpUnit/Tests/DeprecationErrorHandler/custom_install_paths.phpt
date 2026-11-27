--TEST--
Classify deprecations from packages installed outside the Composer vendor directory
--FILE--
<?php

use Symfony\Bridge\PhpUnit\DeprecationErrorHandler\Deprecation;

require __DIR__.'/../../DeprecationErrorHandler/Deprecation.php';
require __DIR__.'/fake_vendor/autoload.php';

$app = __DIR__.'/fake_app/AppService.php';
$first = __DIR__.'/fake_external/first/Service.php';
$second = __DIR__.'/fake_external/first-extra/Service.php';

foreach ([
    [$app, [$app]],
    [$first, [$first, $app]],
    [$first, [$first, $first]],
    [$first, [$first, $second, $app]],
] as [$file, $files]) {
    $trace = array_map(static function ($file) {
        return ['function' => 'call', 'file' => $file];
    }, $files);
    array_unshift($trace, ['function' => 'trigger_error']);
    echo (new Deprecation('Deprecated method', $trace, $file))->getType(), "\n";
}
?>
--EXPECT--
type_self
type_direct
type_direct
type_indirect
