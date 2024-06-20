<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Asset\VersionStrategy;

use Symfony\Component\Asset\Exception\AssetNotFoundException;
use Symfony\Component\Asset\Exception\LogicException;
use Symfony\Component\Asset\Exception\RuntimeException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads the versioned path of an asset from a JSON manifest file.
 *
 * For example, the manifest file might look like this:
 *     {
 *         "main.js": "main.abc123.js",
 *         "css/styles.css": "css/styles.555abc.css"
 *     }
 *
 * You could then ask for the version of "main.js" or "css/styles.css".
 */
class JsonManifestVersionStrategy implements VersionStrategyInterface
{
    private array $manifestData;

    /**
     * @param string $manifestPath Absolute path to the manifest file
     * @param bool   $strictMode   Throws an exception for unknown paths
     */
    public function __construct(
        private string $manifestPath,
        private ?HttpClientInterface $httpClient = null,
        private bool $strictMode = false,
    ) {
        if (null === $this->httpClient && ($scheme = parse_url($this->manifestPath, \PHP_URL_SCHEME)) && str_starts_with($scheme, 'http')) {
            throw new LogicException(\sprintf('The "%s" class needs an HTTP client to use a remote manifest. Try running "composer require symfony/http-client".', self::class));
        }
    }

    /**
     * With a manifest, we don't really know or care about what
     * the version is. Instead, this returns the path to the
     * versioned file.
     */
    public function getVersion(string $path): string
    {
        return $this->applyVersion($path);
    }

    public function applyVersion(string $path): string
    {
        return $this->getManifestPath($path) ?: $path;
    }

    private function getManifestPath(string $path): ?string
    {
        if (!isset($this->manifestData)) {
            if (null !== $this->httpClient && ($scheme = parse_url($this->manifestPath, \PHP_URL_SCHEME)) && str_starts_with($scheme, 'http')) {
                try {
                    $this->manifestData = $this->httpClient->request('GET', $this->manifestPath, [
                        'headers' => ['accept' => 'application/json'],
                    ])->toArray();
                } catch (DecodingExceptionInterface $e) {
                    throw new RuntimeException(\sprintf('Error parsing JSON from asset manifest URL "%s".', $this->manifestPath), 0, $e);
                } catch (ClientExceptionInterface $e) {
                    throw new RuntimeException(\sprintf('Error loading JSON from asset manifest URL "%s".', $this->manifestPath), 0, $e);
                }
            } else {
                if (!is_file($this->manifestPath)) {
                    throw new RuntimeException(\sprintf('Asset manifest file "%s" does not exist. Did you forget to build the assets with npm or yarn?', $this->manifestPath));
                }

                try {
                    $this->manifestData = json_decode(file_get_contents($this->manifestPath), true, flags: \JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    throw new RuntimeException(\sprintf('Error parsing JSON from asset manifest file "%s": ', $this->manifestPath).$e->getMessage(), previous: $e);
                }
            }
        }

        if (isset($this->manifestData[$path])) {
            return $this->manifestData[$path];
        }

        if ($this->strictMode) {
            $message = \sprintf('Asset "%s" not found in manifest "%s".', $path, $this->manifestPath);
            $alternatives = $this->findAlternatives($path, $this->manifestData);
            if (\count($alternatives) > 0) {
                $message .= \sprintf(' Did you mean one of these? "%s".', implode('", "', $alternatives));
            }

            throw new AssetNotFoundException($message, $alternatives);
        }

        return null;
    }

    private function findAlternatives(string $path, array $manifestData): array
    {
        $path = strtolower($path);
        $alternatives = [];

        foreach ($manifestData as $key => $value) {
            $lev = levenshtein($path, strtolower($key));
            if ($lev <= \strlen($path) / 3 || false !== stripos($key, $path)) {
                $alternatives[$key] = isset($alternatives[$key]) ? min($lev, $alternatives[$key]) : $lev;
            }

            $lev = levenshtein($path, strtolower($value));
            if ($lev <= \strlen($path) / 3 || false !== stripos($key, $path)) {
                $alternatives[$key] = isset($alternatives[$key]) ? min($lev, $alternatives[$key]) : $lev;
            }
        }

        asort($alternatives);

        return array_keys($alternatives);
    }
}
