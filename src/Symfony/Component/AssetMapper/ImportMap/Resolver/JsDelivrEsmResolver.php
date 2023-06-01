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

            $filePath = '';
            $i = strpos($packageName, '/');

            if ($i && (!str_starts_with($packageName, '@') || $i = strpos($packageName, '/', $i + 1))) {
                // @vendor/package/filepath or package/filepath
                $filePath = substr($packageName, $i);
                $packageName = substr($packageName, 0, $i);
            }

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
                $cssEntrypointResponses[$options->packageName] = $this->httpClient->request('GET', sprintf(self::URL_PATTERN_ENTRYPOINT, $packageName, $version));
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

            // final URL where it was redirected to
            $url = $response->getInfo('url');
            $content = null;

            if ($options->download) {
                $content = $this->parseJsDelivrImports($response->getContent(), $packagesToRequire, $options->download);
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

            $packagesToRequire[] = new PackageRequireOptions($packageName.$cssFile, $version, $options->download);
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
     * Parses the very specific import syntax used by jsDelivr.
     *
     * Replaces those with normal import "package/name" statements and
     * records the package as a dependency, so it can be downloaded and
     * added to the importmap.
     */
    private function parseJsDelivrImports(string $content, array &$dependencies, bool $download): string
    {
        // imports from jsdelivr follow a predictable format
        $content = preg_replace_callback(self::IMPORT_REGEX, function ($matches) use (&$dependencies, $download) {
            $packageName = $matches[1];
            $version = $matches[2];

            $dependencies[] = new PackageRequireOptions($packageName, $version, $download);

            return sprintf('from"%s"', $packageName);
        }, $content);

        // source maps are not also downloaded - so remove the sourceMappingURL
        $content = preg_replace('{//# sourceMappingURL=.*$}m', '', $content);

        return preg_replace('{/\*# sourceMappingURL=[^ ]*+ \*/}', '', $content);
    }
}
