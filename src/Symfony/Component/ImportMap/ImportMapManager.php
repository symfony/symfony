<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ImportMap;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\VarExporter\VarExporter;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @experimental
 *
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
final class ImportMapManager
{
    public const PROVIDER_JSPM = 'jspm';
    public const PROVIDER_JSPM_SYSTEM = 'jspm.system';
    public const PROVIDER_SKYPACK = 'skypack';
    public const PROVIDER_JSDELIVR = 'jsdelivr';
    public const PROVIDER_UNPKG = 'unpkg';
    public const PROVIDERS = [
        self::PROVIDER_JSPM,
        self::PROVIDER_JSPM_SYSTEM,
        self::PROVIDER_SKYPACK,
        self::PROVIDER_JSDELIVR,
        self::PROVIDER_UNPKG,
    ];

    public const POLYFILL_URL = 'https://ga.jspm.io/npm:es-module-shims@1.7.0/dist/es-module-shims.js';

    /**
     * @see https://regex101.com/r/2cR9Rh/1
     *
     * Partially based on https://github.com/dword-design/package-name-regex
     */
    private const PACKAGE_PATTERN = '/^(?:https?:\/\/[\w\.-]+\/)?(?:(?<registry>\w+):)?(?<package>(?:@[a-z0-9-~][a-z0-9-._~]*\/)?[a-z0-9-~][a-z0-9-._~]*)(?:@(?<version>[\w\._-]+))?(?:(?<subpath>\/.*))?$/';

    private string $baseDir;

    private array $importMap;
    private array $modulesToPreload;
    /**
     * @var array<string, string>
     */
    private array $urlToPath;
    private string $json;

    public function __construct(
        private readonly string $path,
        private readonly string $vendorDir,
        private readonly string $publicDir,
        private readonly string $baseUrl = '/javascript/',
        private readonly string $provider = self::PROVIDER_JSPM,
        private readonly bool $debug = false,
        private ?HttpClientInterface $httpClient = null,
    ) {
        $this->baseDir = dirname($this->path).'/';
        $this->httpClient = $httpClient ?? HttpClient::create(['base_uri' => 'https://api.jspm.io/']);
    }

    public function getModulesToPreload(): array
    {
        $this->buildImportMap();

        return $this->modulesToPreload;
    }

    public function getImportMap(): string
    {
        $this->buildImportMap();

        return $this->json;
    }

    /**
     * Adds or updates packages.
     *
     * @param array<string, PackageOptions> $packages
     */
    public function require(array $packages): void
    {
        $this->createImportMap(false, $packages, []);
    }

    /**
     * Removes packages.
     *
     * @param string[] $packages
     */
    public function remove(array $packages): void
    {
        $this->createImportMap(false, [], $packages);
    }

    /**
     * Updates all existing packages to the latest version.
     */
    public function update(): void
    {
        $this->createImportMap(true, [], []);
    }

    public function getPathForUrl(string $url): ?string
    {
        $this->buildImportMap();

        return $this->urlToPath[$url] ?? null;
    }

    private function loadImportMap(): void
    {
        if (isset($this->importMap)) {
            return;
        }

        $path = $this->path;
        $this->importMap = is_file($path) ? (static fn () => include $path)() : [];
    }

    private function buildImportMap(): void
    {
        if (isset($this->json)) {
            return;
        }

        $this->loadImportMap();
        $this->modulesToPreload = [];
        $this->urlToPath = [];

        $importmap = ['imports' => []];
        foreach ($this->importMap as $packageName => $data) {
            $path = null;
            if (isset($data['url'])) {
                $importmap['imports'][$packageName] = ($data['download'] ?? false) ? $this->baseUrl.$this->digestName($packageName, $path = $this->vendorDir.$packageName.'.js') : $data['url'];
            } elseif (isset($data['path'])) {
                $importmap['imports'][$packageName] = $this->baseUrl.$this->digestName($packageName, $path = $this->baseDir.$data['path']);
            } else {
                continue;
            }

            if ($path) {
                $this->urlToPath[$importmap['imports'][$packageName]] = $path;
            }

            if ($data['preload'] ?? false) {
                $this->modulesToPreload[] = $importmap['imports'][$packageName];
            }
        }

        // Use JSON_UNESCAPED_SLASHES | JSON_HEX_TAG to prevent XSS
        $this->json = json_encode($importmap, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_HEX_TAG);
    }

    /**
     * @param array<string, PackageOptions> $require
     * @param string[]                      $remove
     */
    private function createImportMap(bool $update, array $require, array $remove): void
    {
        $this->loadImportMap();

        foreach ($remove as $packageName) {
            if (!isset($this->importMap[$packageName])) {
                continue;
            }

            $this->cleanup($packageName);
            unset($this->importMap[$packageName]);
        }

        foreach ($require as $packageName => $packageOptions) {
            if (!$packageOptions->path) {
                continue;
            }

            $this->importMap[$packageName] = ['path' => $packageOptions->path];
            if ($packageOptions->preload) {
                $this->importMap[$packageName]['preload'] = true;
            }

            unset($require[$packageName]);
        }

        $install = [];
        $packages = [];
        foreach ($this->importMap ?? [] as $packageName => $data) {
            if (isset($data['path'])) {
                $publicPath = $this->publicDir.$this->digestName($packageName, $path = $this->baseDir.$data['path']);
                if (is_file($publicPath)) {
                    continue;
                }

                $this->cleanup($packageName, false);
                @mkdir(dirname($publicPath), 0777, true);
                copy($path, $publicPath);

                continue;
            }

            $packages[$packageName] = new PackageOptions($data['download'] ?? false, $data['preload'] ?? false);
            if (preg_match(self::PACKAGE_PATTERN, $data['url'] ?? $packageName, $matches)) {
                $constraint = ($matches['registry'] ?? null) ? "{$matches['registry']}:{$matches['package']}" : $matches['package'];

                if (!$update && ($matches['version'] ?? null)) {
                    $constraint .= "@{$matches['version']}";
                }

                $install[$matches['package']] = $constraint;
            }
        }

        foreach ($require as $packageName => $packageOptions) {
            if (preg_match(self::PACKAGE_PATTERN, $packageName, $matches)) {
                $install[$matches['package']] = $packageName;
                $packages[$matches['package']] = $packageOptions;
            }
        }

        $this->jspmGenerate($install, $packages);

        $map = class_exists(VarExporter::class) ? VarExporter::export($this->importMap) : var_export($this->importMap, true);
        file_put_contents($this->path, "<?php\n\nreturn {$map};\n");
    }

    private function jspmGenerate(array $install, array $packages): void
    {
        if (!$install) {
            return;
        }

        $json = [
            'install' => array_values($install),
            'flattenScope' => true,
            'env' => ['browser', 'module', $this->debug ? 'development' : 'production'],
        ];
        if (self::PROVIDER_JSPM !== $this->provider) {
            $json['provider'] = $this->provider;
        }

        $response = $this->httpClient->request('POST', 'generate', [
            'json' => $json,
        ]);

        if (200 !== $response->getStatusCode()) {
            $data = $response->toArray(false);

            if (isset($data['error'])) {
                throw new \RuntimeException($data['error']);
            }

            // Throws the original HttpClient exception
            $response->getHeaders();
        }

        foreach ($response->toArray()['map']['imports'] as $packageName => $url) {
            if ($packages[$packageName]->preload) {
                $this->importMap[$packageName]['preload'] = true;
            } else {
                unset($this->importMap[$packageName]['preload']);
            }

            $vendorPath = $this->vendorDir.$packageName.'.js';
            if (!$packages[$packageName]->download) {
                if ($this->importMap[$packageName]['download'] ?? false) {
                    $this->cleanup($packageName);
                }
                unset($this->importMap[$packageName]['download']);
                $this->importMap[$packageName]['url'] = $url;

                continue;
            }

            $this->importMap[$packageName]['download'] = true;
            if (($this->importMap[$packageName]['url'] ?? null) === $url) {
                continue;
            }

            $this->cleanup($packageName, false);

            $this->importMap[$packageName]['url'] = $url;

            @mkdir(\dirname($vendorPath), 0777, true);
            file_put_contents($vendorPath, $this->httpClient->request('GET', $url)->getContent());

            $publicPath = $this->publicDir.$this->digestName($packageName, $vendorPath);
            @mkdir(\dirname($publicPath), 0777, true);
            copy($vendorPath, $publicPath);
        }
    }

    private function cleanup(string $packageName, bool $cleanEmptyDirectories = true): void
    {
        if ($this->importMap[$packageName]['download'] ?? false) {
            $assetPath = $this->vendorDir.$packageName.'.js';

            if (!is_file($assetPath)) {
                return;
            }

            $publicAssetPath = $this->publicDir.$this->digestName($packageName, $this->vendorDir.$packageName.'.js');

            @unlink($assetPath);
            if ($cleanEmptyDirectories) {
                @rmdir(\dirname($assetPath));
            }

            @unlink($publicAssetPath);
            if ($cleanEmptyDirectories) {
                @rmdir(\dirname($publicAssetPath));
            }

            return;
        }

        if (!($this->importMap[$packageName]['path'] ?? false)) {
            return;
        }

        $assetPath = $this->baseDir.$this->importMap[$packageName]['path'];
        if (!is_file($assetPath)) {
            return;
        }

        $publicAssetPath = $this->publicDir.$this->digestName($packageName, $this->importMap[$packageName]['path']);

        @unlink($publicAssetPath);
        if ($cleanEmptyDirectories) {
            @rmdir(\dirname($publicAssetPath));
        }
    }

    private function digestName(string $packageName, string $path): string
    {
        return sprintf('%s.%s.js', $packageName, hash_file('xxh128', $path));
    }
}
