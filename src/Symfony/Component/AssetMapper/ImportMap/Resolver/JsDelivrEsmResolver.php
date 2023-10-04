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
use Symfony\Component\AssetMapper\ImportMap\ImportMapConfigReader;
use Symfony\Component\AssetMapper\ImportMap\ImportMapEntry;
use Symfony\Component\AssetMapper\ImportMap\ImportMapType;
use Symfony\Component\AssetMapper\ImportMap\PackageRequireOptions;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class JsDelivrEsmResolver implements PackageResolverInterface
{
    public const URL_PATTERN_VERSION = 'https://data.jsdelivr.com/v1/packages/npm/%s/resolved?specifier=%s';
    public const URL_PATTERN_DIST_CSS = 'https://cdn.jsdelivr.net/npm/%s@%s%s';
    public const URL_PATTERN_DIST = self::URL_PATTERN_DIST_CSS.'/+esm';
    public const URL_PATTERN_ENTRYPOINT = 'https://data.jsdelivr.com/v1/packages/npm/%s@%s/entrypoints';

    public const IMPORT_REGEX = '{from"/npm/([^@]*@?\S+?)@([^/]+)/\+esm"}';

    private HttpClientInterface $httpClient;

    public function __construct(
        HttpClientInterface $httpClient = null,
        private readonly string $versionUrlPattern = self::URL_PATTERN_VERSION,
        private readonly string $distUrlPattern = self::URL_PATTERN_DIST,
        private readonly string $distUrlCssPattern = self::URL_PATTERN_DIST_CSS
    ) {
        $this->httpClient = $httpClient ?? HttpClient::create();
    }

    public function resolvePackages(array $packagesToRequire): array
    {
        $resolvedPackages = [];

        resolve_packages:

        // request the version of each package
        $requiredPackages = [];
        foreach ($packagesToRequire as $options) {
            $packageName = trim($options->packageName, '/');
            $constraint = $options->versionConstraint ?? '*';

            // avoid resolving the same package twice
            if (isset($resolvedPackages[$packageName])) {
                continue;
            }

            [$packageName, $filePath] = ImportMapConfigReader::splitPackageNameAndFilePath($packageName);

            $response = $this->httpClient->request('GET', sprintf($this->versionUrlPattern, $packageName, urlencode($constraint)));
            $requiredPackages[] = [$options, $response, $packageName, $filePath, /* resolved version */ null];
        }

        // grab the version of each package & request the contents
        $errors = [];
        $cssEntrypointResponses = [];
        foreach ($requiredPackages as $i => [$options, $response, $packageName, $filePath]) {
            if (200 !== $response->getStatusCode()) {
                $errors[] = [$options->packageName, $response];
                continue;
            }

            $version = $response->toArray()['version'];
            $pattern = str_ends_with($filePath, '.css') ? $this->distUrlCssPattern : $this->distUrlPattern;
            $requiredPackages[$i][1] = $this->httpClient->request('GET', sprintf($pattern, $packageName, $version, $filePath));
            $requiredPackages[$i][4] = $version;

            if (!$filePath) {
                $cssEntrypointResponses[$packageName] = $this->httpClient->request('GET', sprintf(self::URL_PATTERN_ENTRYPOINT, $packageName, $version));
            }
        }

        try {
            ($errors[0][1] ?? null)?->getHeaders();
        } catch (HttpExceptionInterface $e) {
            $response = $e->getResponse();
            $packages = implode('", "', array_column($errors, 0));

            throw new RuntimeException(sprintf('Error %d finding version from jsDelivr for "%s". Check your package names. Response: ', $response->getStatusCode(), $packages).$response->getContent(false), 0, $e);
        }

        // process the contents of each package & add the resolved package
        $packagesToRequire = [];
        foreach ($requiredPackages as [$options, $response, $packageName, $filePath, $version]) {
            if (200 !== $response->getStatusCode()) {
                $errors[] = [$options->packageName, $response];
                continue;
            }

            $packageName = trim($options->packageName, '/');
            $contentType = $response->getHeaders()['content-type'][0] ?? '';
            $type = str_starts_with($contentType, 'text/css') ? ImportMapType::CSS : ImportMapType::JS;
            $resolvedPackages[$packageName] = new ResolvedImportMapPackage($options, $version, $type);

            $packagesToRequire = array_merge($packagesToRequire, $this->fetchPackageRequirementsFromImports($response->getContent()));
        }

        try {
            ($errors[0][1] ?? null)?->getHeaders();
        } catch (HttpExceptionInterface $e) {
            $response = $e->getResponse();
            $packages = implode('", "', array_column($errors, 0));

            throw new RuntimeException(sprintf('Error %d requiring packages from jsDelivr for "%s". Check your package names. Response: ', $response->getStatusCode(), $packages).$response->getContent(false), 0, $e);
        }

        // process any pending CSS entrypoints
        $errors = [];
        foreach ($cssEntrypointResponses as $package => $cssEntrypointResponse) {
            if (200 !== $cssEntrypointResponse->getStatusCode()) {
                $errors[] = [$package, $cssEntrypointResponse];
                continue;
            }

            $entrypoints = $cssEntrypointResponse->toArray()['entrypoints'] ?? [];
            $cssFile = $entrypoints['css']['file'] ?? null;

            if (!$cssFile) {
                continue;
            }

            $packagesToRequire[] = new PackageRequireOptions($package.$cssFile, $version);
        }

        try {
            ($errors[0][1] ?? null)?->getHeaders();
        } catch (HttpExceptionInterface $e) {
            $response = $e->getResponse();
            $packages = implode('", "', array_column($errors, 0));

            throw new RuntimeException(sprintf('Error %d checking for a CSS entrypoint for packages from jsDelivr for "%s". Response: ', $response->getStatusCode(), $packages).$response->getContent(false), 0, $e);
        }

        if ($packagesToRequire) {
            goto resolve_packages;
        }

        return array_values($resolvedPackages);
    }

    /**
     * @param ImportMapEntry[] $importMapEntries
     *
     * @return array<string, string>
     */
    public function downloadPackages(array $importMapEntries, callable $progressCallback = null): array
    {
        $responses = [];

        foreach ($importMapEntries as $package => $entry) {
            $pattern = ImportMapType::CSS === $entry->type ? $this->distUrlCssPattern : $this->distUrlPattern;
            $url = sprintf($pattern, $entry->packageName, $entry->version, $entry->filePath);

            $responses[$package] = $this->httpClient->request('GET', $url);
        }

        $errors = [];
        $contents = [];
        foreach ($responses as $package => $response) {
            if (200 !== $response->getStatusCode()) {
                $errors[] = [$package, $response];
                continue;
            }

            if ($progressCallback) {
                $progressCallback($package, 'started', $response, \count($responses));
            }
            $contents[$package] = $this->makeImportsBare($response->getContent());
            if ($progressCallback) {
                $progressCallback($package, 'finished', $response, \count($responses));
            }
        }

        try {
            ($errors[0][1] ?? null)?->getHeaders();
        } catch (HttpExceptionInterface $e) {
            $response = $e->getResponse();
            $packages = implode('", "', array_column($errors, 0));

            throw new RuntimeException(sprintf('Error %d downloading packages from jsDelivr for "%s". Check your package names. Response: ', $response->getStatusCode(), $packages).$response->getContent(false), 0, $e);
        }

        return $contents;
    }

    /**
     * Parses the very specific import syntax used by jsDelivr.
     *
     * Replaces those with normal import "package/name" statements and
     * records the package as a dependency, so it can be downloaded and
     * added to the importmap.
     *
     * @return PackageRequireOptions[]
     */
    private function fetchPackageRequirementsFromImports(string $content): array
    {
        // imports from jsdelivr follow a predictable format
        preg_match_all(self::IMPORT_REGEX, $content, $matches);
        $dependencies = [];
        foreach ($matches[1] as $index => $packageName) {
            $version = $matches[2][$index];

            $dependencies[] = new PackageRequireOptions($packageName, $version);
        }

        return $dependencies;
    }

    /**
     * Parses the very specific import syntax used by jsDelivr.
     *
     * Replaces those with normal import "package/name" statements.
     */
    private function makeImportsBare(string $content): string
    {
        $content = preg_replace_callback(self::IMPORT_REGEX, fn ($m) => sprintf('from"%s"', $m[1]), $content);

        // source maps are not also downloaded - so remove the sourceMappingURL
        $content = preg_replace('{//# sourceMappingURL=.*$}m', '', $content);

        return preg_replace('{/\*# sourceMappingURL=[^ ]*+ \*/}', '', $content);
    }
}
