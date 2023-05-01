<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper;

/**
 * Finds and returns assets in the pipeline.
 *
 * @experimental
 *
 * @final
 */
class AssetMapper implements AssetMapperInterface
{
    public const MANIFEST_FILE_NAME = 'manifest.json';
    // source: https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/MIME_types/Common_types
    private const EXTENSIONS_MAP = [
        'aac' => 'audio/aac',
         'abw' => 'application/x-abiword',
         'arc' => 'application/x-freearc',
         'avif' => 'image/avif',
         'avi' => 'video/x-msvideo',
         'azw' => 'application/vnd.amazon.ebook',
         'bin' => 'application/octet-stream',
         'bmp' => 'image/bmp',
         'bz' => 'application/x-bzip',
         'bz2' => 'application/x-bzip2',
         'cda' => 'application/x-cdf',
         'csh' => 'application/x-csh',
         'css' => 'text/css',
         'csv' => 'text/csv',
         'doc' => 'application/msword',
         'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
         'eot' => 'application/vnd.ms-fontobject',
         'epub' => 'application/epub+zip',
         'gz' => 'application/gzip',
         'gif' => 'image/gif',
         'htm' => 'text/html',
         'html' => 'text/html',
         'ico' => 'image/vnd.microsoft.icon',
         'ics' => 'text/calendar',
         'jar' => 'application/java-archive',
         'jpeg' => 'image/jpeg',
         'jpg' => 'image/jpeg',
         'js' => 'text/javascript',
         'json' => 'application/json',
         'jsonld' => 'application/ld+json',
         'mid' => 'audio/midi',
         'midi' => 'audio/midi',
         'mjs' => 'text/javascript',
         'mp3' => 'audio/mpeg',
         'mp4' => 'video/mp4',
         'mpeg' => 'video/mpeg',
         'mpkg' => 'application/vnd.apple.installer+xml',
         'odp' => 'application/vnd.oasis.opendocument.presentation',
         'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
         'odt' => 'application/vnd.oasis.opendocument.text',
         'oga' => 'audio/ogg',
         'ogv' => 'video/ogg',
         'ogx' => 'application/ogg',
         'opus' => 'audio/opus',
         'otf' => 'font/otf',
         'png' => 'image/png',
         'pdf' => 'application/pdf',
         'php' => 'application/x-httpd-php',
         'ppt' => 'application/vnd.ms-powerpoint',
         'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
         'rar' => 'application/vnd.rar',
         'rtf' => 'application/rtf',
         'sh' => 'application/x-sh',
         'svg' => 'image/svg+xml',
         'tar' => 'application/x-tar',
         'tif' => 'image/tiff',
         'tiff' => 'image/tiff',
         'ts' => 'video/mp2t',
         'ttf' => 'font/ttf',
         'txt' => 'text/plain',
         'vsd' => 'application/vnd.visio',
         'wav' => 'audio/wav',
         'weba' => 'audio/webm',
         'webm' => 'video/webm',
         'webp' => 'image/webp',
         'woff' => 'font/woff',
         'woff2' => 'font/woff2',
    ];
    private const PREDIGESTED_REGEX = '/-([0-9a-zA-Z]{7,128}\.digested)/';

    private ?array $manifestData = null;
    private array $fileContentsCache = [];
    private array $assetsBeingCreated = [];
    private readonly string $publicPrefix;
    private array $extensionsMap = [];

    private array $assetsCache = [];

    public function __construct(
        private readonly AssetMapperRepository $mapperRepository,
        private readonly AssetMapperCompiler $compiler,
        private readonly string $projectRootDir,
        string $publicPrefix = '/assets/',
        private readonly string $publicDirName = 'public',
        array $extensionsMap = [],
    ) {
        // ensure that the public prefix always ends with a single slash
        $this->publicPrefix = rtrim($publicPrefix, '/').'/';
        $this->extensionsMap = array_merge(self::EXTENSIONS_MAP, $extensionsMap);
    }

    public function getPublicPrefix(): string
    {
        return $this->publicPrefix;
    }

    public function getAsset(string $logicalPath): ?MappedAsset
    {
        if (\in_array($logicalPath, $this->assetsBeingCreated, true)) {
            throw new \RuntimeException(sprintf('Circular reference detected while creating asset for "%s": "%s".', $logicalPath, implode(' -> ', $this->assetsBeingCreated).' -> '.$logicalPath));
        }

        if (!isset($this->assetsCache[$logicalPath])) {
            $this->assetsBeingCreated[] = $logicalPath;

            $filePath = $this->mapperRepository->find($logicalPath);
            if (null === $filePath) {
                return null;
            }

            $asset = new MappedAsset($logicalPath);
            $this->assetsCache[$logicalPath] = $asset;
            $asset->setSourcePath($filePath);

            $asset->setMimeType($this->getMimeType($logicalPath));
            $publicPath = $this->getPublicPath($logicalPath);
            $asset->setPublicPath($publicPath);
            [$digest, $isPredigested] = $this->getDigest($asset);
            $asset->setDigest($digest, $isPredigested);
            $asset->setContent($this->calculateContent($asset));

            array_pop($this->assetsBeingCreated);
        }

        return $this->assetsCache[$logicalPath];
    }

    /**
     * @return MappedAsset[]
     */
    public function allAssets(): array
    {
        $assets = [];
        foreach ($this->mapperRepository->all() as $logicalPath => $filePath) {
            $asset = $this->getAsset($logicalPath);
            if (null === $asset) {
                throw new \LogicException(sprintf('Asset "%s" could not be found.', $logicalPath));
            }
            $assets[] = $asset;
        }

        return $assets;
    }

    public function getAssetFromSourcePath(string $sourcePath): ?MappedAsset
    {
        $logicalPath = $this->mapperRepository->findLogicalPath($sourcePath);
        if (null === $logicalPath) {
            return null;
        }

        return $this->getAsset($logicalPath);
    }

    public function getPublicPath(string $logicalPath): ?string
    {
        $manifestData = $this->loadManifest();
        if (isset($manifestData[$logicalPath])) {
            return $manifestData[$logicalPath];
        }

        $filePath = $this->mapperRepository->find($logicalPath);
        if (null === $filePath) {
            return null;
        }

        // grab the Asset - first look in the cache, as it may only be partially created
        $asset = $this->assetsCache[$logicalPath] ?? $this->getAsset($logicalPath);
        [$digest, $isPredigested] = $this->getDigest($asset);

        if ($isPredigested) {
            return $this->publicPrefix.$logicalPath;
        }

        return $this->publicPrefix.preg_replace_callback('/\.(\w+)$/', function ($matches) use ($digest) {
            return "-{$digest}{$matches[0]}";
        }, $logicalPath);
    }

    public static function isPathPredigested(string $path): bool
    {
        return 1 === preg_match(self::PREDIGESTED_REGEX, $path);
    }

    public function getPublicAssetsFilesystemPath(): string
    {
        return rtrim(rtrim($this->projectRootDir, '/').'/'.$this->publicDirName.$this->publicPrefix, '/');
    }

    /**
     * Returns an array of "string digest" and "bool predigested".
     *
     * @return array{0: string, 1: bool}
     */
    private function getDigest(MappedAsset $asset): array
    {
        // check for a pre-digested file
        if (1 === preg_match(self::PREDIGESTED_REGEX, $asset->logicalPath, $matches)) {
            return [$matches[1], true];
        }

        return [
            hash('xxh128', $this->calculateContent($asset)),
            false,
        ];
    }

    private function getMimeType(string $logicalPath): ?string
    {
        $filePath = $this->mapperRepository->find($logicalPath);
        if (null === $filePath) {
            return null;
        }

        $extension = pathinfo($logicalPath, \PATHINFO_EXTENSION);

        if (!isset($this->extensionsMap[$extension])) {
            throw new \LogicException(sprintf('The file extension "%s" from "%s" does not correspond to any known types in the asset mapper. To support this extension, configure framework.asset_mapper.extensions.', $extension, $logicalPath));
        }

        return $this->extensionsMap[$extension];
    }

    private function calculateContent(MappedAsset $asset): string
    {
        if (isset($this->fileContentsCache[$asset->logicalPath])) {
            return $this->fileContentsCache[$asset->logicalPath];
        }

        $content = file_get_contents($asset->getSourcePath());
        $content = $this->compiler->compile($content, $asset, $this);

        $this->fileContentsCache[$asset->logicalPath] = $content;

        return $content;
    }

    private function loadManifest(): array
    {
        if (null === $this->manifestData) {
            $path = $this->getPublicAssetsFilesystemPath().'/'.self::MANIFEST_FILE_NAME;

            if (!file_exists($path)) {
                $this->manifestData = [];
            } else {
                $this->manifestData = json_decode(file_get_contents($path), true);
            }
        }

        return $this->manifestData;
    }
}
