<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Bundle\FrameworkBundle\Tests\Functional\app\ConfigCacheFreshness\FreshnessKernel;
use Symfony\Bundle\FrameworkBundle\Tests\Functional\app\ConfigCacheFreshness\MutatingTranslationStream;
use Symfony\Bundle\FrameworkBundle\Tests\Functional\app\ConfigCacheFreshness\Mutation;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Config\Resource\SelfCheckingResourceChecker;
use Symfony\Component\HttpFoundation\Request;

// Handles one request per PHP process. The parent test runs this script three times against the same input and cache directories.
// Compiled values and build counts show the interrupted build, the corrective request, and reuse of the corrected cache.
$loader = require $argv[1];
$loader->addPsr4('FreshnessFixture\\', $argv[2].'/classes');
Mutation::$directory = $argv[2];
Mutation::$mode = $argv[3];
stream_wrapper_register('translationcapture', MutatingTranslationStream::class);
if (str_starts_with($argv[3], 'reflection')) {
    // Model a request that began before the class was loaded and its source was edited.
    $_SERVER['REQUEST_TIME'] = time() - 10;
}

$kernel = 'generated' === $argv[3] ? new FreshnessFixture\GeneratedKernel($argv[2], $argv[3]) : new FreshnessKernel($argv[2], $argv[3]);
$request = Request::create('/');
$response = $kernel->handle($request);
if (200 !== $response->getStatusCode()) {
    throw new RuntimeException($response->getContent());
}
$container = $kernel->getContainer();
$cache = $container->getParameter('kernel.build_dir').'/'.$container->getParameter('kernel.container_class').'.php';
$result = [
    'pid' => getmypid(),
    'response' => $response->getContent(),
    'parameter' => $container->getParameter('freshness.value'),
    'builds' => (int) file_get_contents($argv[2].'/builds'),
    'debug' => $kernel->isDebug(),
    'cache' => $cache,
    'cache_mtime' => filemtime($cache),
    'source_mtime' => filemtime($argv[2].'/config/services.yml'),
];
if (str_starts_with($argv[3], 'route-')) {
    $router = $container->get('router');
    if ('route-between-dumps' === $argv[3] && !is_file($argv[2].'/changed')) {
        Mutation::change($argv[2].'/config/routes.yml');
        touch($argv[2].'/config/routes.yml', time() - 1);
        clearstatcache();
    }
    $result['generated'] = $router->generate('dynamic');
    $result['matched'] = $router->match($result['generated'])['_route'];
}
if (str_starts_with($argv[3], 'translation-')) {
    $translator = $container->get('translator');
    $key = 'translation-dat' === $argv[3] || 'translation-res' === $argv[3] ? 'symfony' : 'key';
    $result['message'] = $translator->trans($key, [], 'probe', 'en');
    $result['fallback'] = $translator->trans($key, [], 'probe', 'fr');
    if (str_starts_with($argv[3], 'translation-directory')) {
        $result['added_message'] = $translator->trans('key', [], 'added', 'en');
        $result['removed_message'] = $translator->trans('key', [], 'removed', 'fr');
    }
}
if ('percent' === $argv[3]) {
    $result['message'] = $container->get('translator')->trans('key', [], 'some%missing%', 'en');
}
if ('glob' === $argv[3]) {
    $result['added'] = $container->has('FreshnessFixture\\Added') ? $container->get('FreshnessFixture\\Added')->value : null;
}
if (str_starts_with($argv[3], 'reflection')) {
    $result['injected'] = $container->get('FreshnessFixture\\Service')->value;
    $result['reflection_source_mtime'] = filemtime($argv[2].'/classes/Service.php');
    $result['request_start'] = $_SERVER['REQUEST_TIME'];
}
if ('asset' === $argv[3]) {
    $asset = $container->get('fixture.asset_mapper')->getAsset('app.css');
    $assetRequest = Request::create($asset->publicPath);
    $assetResponse = $kernel->handle($assetRequest);
    if (200 !== $assetResponse->getStatusCode()) {
        throw new RuntimeException('Mapped asset did not return HTTP 200.');
    }
    ob_start();
    $assetResponse->sendContent();
    $result['asset'] = ob_get_clean();
    $kernel->terminate($assetRequest, $assetResponse);
}
$result['dumps'] = is_file($argv[2].'/dumps') ? array_count_values(file($argv[2].'/dumps', \FILE_IGNORE_NEW_LINES)) : [];
if (str_starts_with($argv[3], 'translation-')) {
    $result['catalogue_mtimes'] = [];
    foreach (array_keys($result['dumps']) as $path) {
        if (str_contains($path, '/translations/')) {
            $result['catalogue_mtimes'][$path] = filemtime($path);
        }
    }
}
if ('route-directory-equal' === $argv[3]) {
    $result['directory_mtime'] = filemtime($argv[2].'/config/routes');
    foreach (array_keys($result['dumps']) as $path) {
        if (str_contains($path, 'url_matching_routes')) {
            $result['route_cache_mtime'] = filemtime($path);
            break;
        }
    }
}
if ('route-checker' === $argv[3]) {
    foreach (array_keys($result['dumps']) as $path) {
        if (str_contains($path, 'url_matching_routes')) {
            $routerCache = $path;
            break;
        }
    }
    $snapshots = [];
    foreach ([$routerCache, $cache] as $path) {
        foreach (unserialize(file_get_contents($path.'.meta')) as $resource) {
            if ($resource instanceof FileResource && realpath($argv[2].'/config/routes.yml') === $resource->getResource()) {
                $snapshots[] = $resource;
                break;
            }
        }
    }
    if (2 !== count($snapshots)) {
        throw new RuntimeException('The actual container and router metadata must both contain the shared routes file.');
    }
    $timestamp = max(filemtime($cache), filemtime($routerCache));
    $checker = new SelfCheckingResourceChecker();
    $result['checks'] = ['pid' => getmypid(), 'calls' => 0, 'timestamp' => $timestamp];
    foreach ($snapshots as $resource) {
        $result['checks']['direct'][] = $resource->isFresh($timestamp);
        $result['checks']['verdicts'][] = $checker->isFresh($resource, $timestamp);
        ++$result['checks']['calls'];
        $result['checks']['identities'][] = (string) $resource;
        $result['checks']['snapshots'][] = serialize($resource);
    }
}
if ('generated' === $argv[3]) {
    $result['generated_hashes'] = [
        'reference' => hash_file('xxh128', $argv[2].'/config/reference.php'),
        'schema' => hash_file('xxh128', $argv[2].'/config/schema.json'),
    ];
}
$kernel->terminate($request, $response);
$kernel->shutdown();

echo json_encode($result, \JSON_THROW_ON_ERROR);
