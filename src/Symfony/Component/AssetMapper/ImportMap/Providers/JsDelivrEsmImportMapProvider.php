<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\ImportMap\Providers;

use Symfony\Component\AssetMapper\ImportMap\PackageRequireOptions;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @experimental
 */
class JsDelivrEsmImportMapProvider implements ImportMapPackageProviderInterface
{
    private HttpClientInterface $httpClient;
    private array $resolvedPackageNames = [];

    public function __construct(
        HttpClientInterface $httpClient = null
    ) {
        $this->httpClient = $httpClient ?? HttpClient::create();
    }

    public function resolvePackages(array $packagesToRequire): array
    {
        // reset internal tracker
        $this->resolvedPackageNames = [];

        return $this->doResolvePackages($packagesToRequire);
    }

    /**
     * @param PackageRequireOptions[] $packagesToRequire
     *
     * @return ResolvedImportMapPackage[] The import map entries that should be added
     */
    private function doResolvePackages(array $packagesToRequire): array
    {
        $resolvedPackages = [];
        foreach ($packagesToRequire as $requireOptions) {
            $packageName = $requireOptions->packageName;

            // avoid resolving the same package twice
            if (\in_array($packageName, $this->resolvedPackageNames, true)) {
                continue;
            }

            $filePath = null;
            // scoped packages
            if (str_starts_with($packageName, '@')) {
                if (substr_count($packageName, '/') >= 2) {
                    // @some-vendor/package/filepath
                    $parts = explode('/', $packageName);
                    $packageName = implode('/', \array_slice($parts, 0, 2));
                    $filePath = implode('/', \array_slice($parts, 2));
                }
            } elseif (substr_count($packageName, '/') >= 1) {
                $filePath = substr($packageName, strpos($packageName, '/') + 1);
                $packageName = substr($packageName, 0, strpos($packageName, '/'));
            }

            $version = $this->findVersion($packageName, $requireOptions->versionConstraint);

            $url = sprintf('https://esm.run/%s@%s', $packageName, $version);
            if ($filePath) {
                $url .= '/'.$filePath;
            }

            $response = $this->httpClient->request('GET', $url);
            if (200 !== $response->getStatusCode()) {
                throw new \RuntimeException(sprintf('Error requiring JavaScript package from jsDelivr using URL "%s". Check your package name. Response: ', $url, $response->getContent(false)));
            }
            // final URL where it was redirected to
            $url = $response->getInfo('url');

            /** @var array{package: string, version: string} $dependencies */
            $dependencies = [];
            $content = null;

            if ($requireOptions->download) {
                $content = $response->getContent();

                $content = $this->parseJsDelivrImports($content, $dependencies);
            }

            $resolvedPackages[] = new ResolvedImportMapPackage($requireOptions, $url, $content);
            $this->resolvedPackageNames[] = $requireOptions->packageName;

            $dependencyPackageRequires = [];
            foreach ($dependencies as $dependencyPackageName => $dependencyVersion) {
                $dependencyPackageRequires[] = new PackageRequireOptions($dependencyPackageName, $dependencyVersion, $requireOptions->download, $requireOptions->preload);
            }

            $resolvedPackages = array_merge(
                $resolvedPackages,
                $this->doResolvePackages($dependencyPackageRequires)
            );
        }

        return $resolvedPackages;
    }

    /**
     * Parses the very specific import syntax used by jsDelivr.
     *
     * Replaces those with normal import "package/name" statements and
     * records the package as a dependency, so it can be downloaded and
     * added to the importmap.
     */
    private function parseJsDelivrImports(string $content, array &$dependencies): string
    {
        // imports from jsdelivr follow a predictable format
        $regex = '/from"\/npm\/([^\@]*@?[^\@]+)@([^\/]+)\/\+esm"/';
        $content = preg_replace_callback($regex, function ($matches) use (&$dependencies) {
            $packageName = $matches[1];
            $version = $matches[2];

            $dependencies[$packageName] = $version;

            return sprintf('from"%s"', $packageName);
        }, $content);

        // source maps are not also downloaded - so remove the sourceMappingURL
        $sourceMapRegex = '/\/\/\# sourceMappingURL=.*\n?/';

        return preg_replace($sourceMapRegex, '', $content);
    }

    private function findVersion(string $packageName, ?string $versionConstraint): string
    {
        $queryString = null === $versionConstraint ? '' : '?'.http_build_query([
            'specifier' => $versionConstraint,
        ]);

        $response = $this->httpClient->request('GET', 'https://data.jsdelivr.com/v1/packages/npm/'.$packageName.'/resolved'.$queryString);
        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException(sprintf('Error finding version for JavaScript package "%s" from jsDelivr. Check your package name. Response: ', $packageName, $response->getContent(false)));
        }

        return $response->toArray()['version'];
    }
}
