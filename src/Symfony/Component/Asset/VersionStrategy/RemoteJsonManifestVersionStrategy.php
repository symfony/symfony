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

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads the versioned path of an asset from a remote JSON manifest file.
 *
 * For example, the manifest file might look like this:
 *     {
 *         "main.js": "main.abc123.js",
 *         "css/styles.css": "css/styles.555abc.css"
 *     }
 *
 * You could then ask for the version of "main.js" or "css/styles.css".
 */
class RemoteJsonManifestVersionStrategy implements VersionStrategyInterface
{
    private $manifestData;
    private $manifestUrl;
    private $httpClient;

    /**
     * @param string $manifestUrl Absolute URL to the manifest file
     */
    public function __construct(string $manifestUrl, HttpClientInterface $httpClient)
    {
        $this->manifestUrl = $manifestUrl;
        $this->httpClient = $httpClient;
    }

    /**
     * With a manifest, we don't really know or care about what
     * the version is. Instead, this returns the path to the
     * versioned file.
     */
    public function getVersion(string $path)
    {
        return $this->applyVersion($path);
    }

    public function applyVersion(string $path)
    {
        if (null === $this->manifestData) {
            $this->manifestData = $this->httpClient->request('GET', $this->manifestUrl, [
                'headers' => ['accept' => 'application/json'],
            ])->toArray();
        }

        return $this->manifestData[$path] ?? $path;
    }
}
