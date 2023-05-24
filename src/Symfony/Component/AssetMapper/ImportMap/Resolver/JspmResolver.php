<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\ImportMap\Resolver;

use Symfony\Component\AssetMapper\Exception\RuntimeException;
use Symfony\Component\AssetMapper\ImportMap\ImportMapManager;
use Symfony\Component\AssetMapper\ImportMap\PackageRequireOptions;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @experimental
 */
final class JspmResolver implements PackageResolverInterface
{
    public const BASE_URI = 'https://api.jspm.io/';

    private HttpClientInterface $httpClient;

    public function __construct(
        HttpClientInterface $httpClient = null,
        private readonly string $provider = ImportMapManager::PROVIDER_JSPM,
        private readonly string $baseUri = self::BASE_URI,
    ) {
        $this->httpClient = $httpClient ?? HttpClient::create();
    }

    public function resolvePackages(array $packagesToRequire): array
    {
        if (!$packagesToRequire) {
            return [];
        }

        $installData = [];
        $packageRequiresByName = [];
        foreach ($packagesToRequire as $options) {
            $constraint = $options->packageName;
            if (null !== $options->versionConstraint) {
                $constraint .= '@'.$options->versionConstraint;
            }
            if (null !== $options->registryName) {
                $constraint = sprintf('%s:%s', $options->registryName, $constraint);
            }
            $installData[] = $constraint;
            $packageRequiresByName[$options->packageName] = $options;
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

        $response = $this->httpClient->request('POST', 'generate', [
            'base_uri' => $this->baseUri,
            'json' => $json,
        ]);

        if (200 !== $response->getStatusCode()) {
            $data = $response->toArray(false);

            if (isset($data['error'])) {
                throw new RuntimeException('Error requiring JavaScript package: '.$data['error']);
            }

            // Throws the original HttpClient exception
            $response->getHeaders();
        }

        // if we're requiring just one package, in case it has any peer deps, match the preload
        $defaultOptions = $packagesToRequire[0];

        $resolvedPackages = [];
        foreach ($response->toArray()['map']['imports'] as $packageName => $url) {
            $options = $packageRequiresByName[$packageName] ?? new PackageRequireOptions($packageName, null, $defaultOptions->download, $defaultOptions->preload);
            $resolvedPackages[] = [$options, $url, $options->download ? $this->httpClient->request('GET', $url, ['base_uri' => $this->baseUri]) : null];
        }

        try {
            return array_map(fn ($args) => new ResolvedImportMapPackage($args[0], $args[1], $args[2]?->getContent()), $resolvedPackages);
        } catch (\Throwable $e) {
            foreach ($resolvedPackages as $args) {
                $args[2]?->cancel();
            }

            throw $e;
        }
    }
}
