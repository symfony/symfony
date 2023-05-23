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

use Symfony\Component\AssetMapper\ImportMap\ImportMapManager;
use Symfony\Component\AssetMapper\ImportMap\PackageRequireOptions;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @experimental
 */
class JspmImportMapProvider implements ImportMapPackageProviderInterface
{
    private HttpClientInterface $httpClient;

    public function __construct(
        private readonly string $provider = ImportMapManager::PROVIDER_JSPM,
        HttpClientInterface $httpClient = null
    ) {
        $this->httpClient = $httpClient ?? HttpClient::create(['base_uri' => 'https://api.jspm.io/']);
    }

    public function resolvePackages(array $packagesToRequire): array
    {
        if (!$packagesToRequire) {
            return [];
        }

        $installData = [];
        $packageRequiresByName = [];
        foreach ($packagesToRequire as $requireOptions) {
            $constraint = $requireOptions->packageName;
            if (null !== $requireOptions->versionConstraint) {
                $constraint .= '@'.$requireOptions->versionConstraint;
            }
            if (null !== $requireOptions->registryName) {
                $constraint = sprintf('%s:%s', $requireOptions->registryName, $constraint);
            }
            $installData[] = $constraint;
            $packageRequiresByName[$requireOptions->packageName] = $requireOptions;
        }

        $json = [
            'install' => $installData,
            'flattenScope' => true,
            // always grab production-ready assets
            'env' => ['browser', 'module', 'production'],
        ];
        if (ImportMapManager::PROVIDER_JSPM !== $this->provider) {
            $json['provider'] = $this->provider;
        }

        $response = $this->httpClient->request('POST', 'https://api.jspm.io/generate', [
            'json' => $json,
        ]);

        if (200 !== $response->getStatusCode()) {
            $data = $response->toArray(false);

            if (isset($data['error'])) {
                throw new \RuntimeException('Error requiring JavaScript package: '.$data['error']);
            }

            // Throws the original HttpClient exception
            $response->getHeaders();
        }

        // if we're requiring just one package, in case it has any peer deps, match the preload
        $defaultRequireOptions = $packagesToRequire[0];

        $resolvedPackages = [];
        foreach ($response->toArray()['map']['imports'] as $packageName => $url) {
            $requireOptions = $packageRequiresByName[$packageName] ?? new PackageRequireOptions($packageName, null, $defaultRequireOptions->download, $defaultRequireOptions->preload);
            $content = null;

            if ($requireOptions->download) {
                $content = $this->httpClient->request('GET', $url)->getContent();
            }

            $resolvedPackages[] = new ResolvedImportMapPackage($requireOptions, $url, $content);
        }

        return $resolvedPackages;
    }
}
