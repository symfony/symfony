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
use Symfony\Component\AssetMapper\ImportMap\PackageRequireOptions;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @experimental
 */
final class JsDelivrEsmResolver implements PackageResolverInterface
{
    public const URL_PATTERN_VERSION = 'https://data.jsdelivr.com/v1/packages/npm/%s/resolved?specifier=%s';
    public const URL_PATTERN_DIST = 'https://cdn.jsdelivr.net/npm/%s@%s%s/+esm';

    private HttpClientInterface $httpClient;

    public function __construct(
        HttpClientInterface $httpClient = null,
        private readonly string $versionUrlPattern = self::URL_PATTERN_VERSION,
        private readonly string $distUrlPattern = self::URL_PATTERN_DIST,
    ) {
        $this->httpClient = $httpClient ?? HttpClient::create();
    }

    public function resolvePackages(array $packagesToRequire): array
    {
        $resolvedPackages = [];

        resolve_packages:

        $requiredPackages = [];
        foreach ($packagesToRequire as $options) {
            $packageName = trim($options->packageName, '/');
            $constraint = $options->versionConstraint ?? '*';

            // avoid resolving the same package twice
            if (isset($resolvedPackages[$packageName])) {
                continue;
            }

            $filePath = '';
            $i = strpos($packageName, '/');

            if ($i && (!str_starts_with($packageName, '@') || $i = strpos($packageName, '/', $i + 1))) {
                // @vendor/package/filepath or package/filepath
                $filePath = substr($packageName, $i);
                $packageName = substr($packageName, 0, $i);
            }

            $response = $this->httpClient->request('GET', sprintf($this->versionUrlPattern, $packageName, urlencode($constraint)));
            $requiredPackages[] = [$options, $response, $packageName, $filePath, $options];
        }

        $errors = [];
        foreach ($requiredPackages as $i => [$options, $response, $packageName, $filePath]) {
            if (200 !== $response->getStatusCode()) {
                $errors[] = [$options->packageName, $response];
                continue;
            }

            $version = $response->toArray()['version'];
            $requiredPackages[$i][1] = $this->httpClient->request('GET', sprintf($this->distUrlPattern, $packageName, $version, $filePath));
        }

        try {
            ($errors[0][1] ?? null)?->getHeaders();
        } catch (HttpExceptionInterface $e) {
            $response = $e->getResponse();
            $packages = implode('", "', array_column($errors, 0));

            throw new RuntimeException(sprintf('Error %d finding version from jsDelivr for "%s". Check your package names. Response: ', $response->getStatusCode(), $packages).$response->getContent(false), 0, $e);
        }

        $packagesToRequire = [];
        foreach ($requiredPackages as [$options, $response]) {
            if (200 !== $response->getStatusCode()) {
                $errors[] = [$options->packageName, $response];
                continue;
            }

            // final URL where it was redirected to
            $url = $response->getInfo('url');
            $content = null;

            if ($options->download) {
                $content = $this->parseJsDelivrImports($response->getContent(), $packagesToRequire, $options->download, $options->preload);
            }

            $packageName = trim($options->packageName, '/');
            $resolvedPackages[$packageName] = new ResolvedImportMapPackage($options, $url, $content);
        }

        try {
            ($errors[0][1] ?? null)?->getHeaders();
        } catch (HttpExceptionInterface $e) {
            $response = $e->getResponse();
            $packages = implode('", "', array_column($errors, 0));

            throw new RuntimeException(sprintf('Error %d requiring packages from jsDelivr for "%s". Check your package names. Response: ', $response->getStatusCode(), $packages).$response->getContent(false), 0, $e);
        }

        if ($packagesToRequire) {
            goto resolve_packages;
        }

        return array_values($resolvedPackages);
    }

    /**
     * Parses the very specific import syntax used by jsDelivr.
     *
     * Replaces those with normal import "package/name" statements and
     * records the package as a dependency, so it can be downloaded and
     * added to the importmap.
     */
    private function parseJsDelivrImports(string $content, array &$dependencies, bool $download, bool $preload): string
    {
        // imports from jsdelivr follow a predictable format
        $regex = '{from"/npm/([^@]*@?[^@]+)@([^/]+)/\+esm"}';
        $content = preg_replace_callback($regex, function ($matches) use (&$dependencies, $download, $preload) {
            $packageName = $matches[1];
            $version = $matches[2];

            $dependencies[] = new PackageRequireOptions($packageName, $version, $download, $preload);

            return sprintf('from"%s"', $packageName);
        }, $content);

        // source maps are not also downloaded - so remove the sourceMappingURL
        return preg_replace('{//# sourceMappingURL=.*$}m', '', $content);
    }
}
