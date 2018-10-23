<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Mercure;

/**
 * Publishes an update to the hub.
 *
 * Can be used as a Symfony Messenger handler too.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 *
 * @experimental
 */
final class Publisher
{
    private $hubUrl;
    private $jwtProvider;
    private $httpClient;

    /**
     * @param callable(): string                                        $jwtProvider
     * @param null|callable(string, callable(): string, string): string $httpClient
     */
    public function __construct(string $hubUrl, callable $jwtProvider, callable $httpClient = null)
    {
        $this->hubUrl = $hubUrl;
        $this->jwtProvider = $jwtProvider;
        $this->httpClient = $httpClient ?? array($this, 'publish');
    }

    public function __invoke(Update $update): string
    {
        $postData = array(
            'topic' => $update->getTopics(),
            'data' => $update->getData(),
            'target' => $update->getTargets(),
            'id' => $update->getId(),
            'type' => $update->getType(),
            'retry' => $update->getRetry(),
        );

        $jwt = ($this->jwtProvider)();
        $this->validateJwt($jwt);

        return ($this->httpClient)($this->hubUrl, $jwt, $this->buildQuery($postData));
    }

    /**
     * Similar to http_build_query but doesn't add the brackets in keys for array values and skip null values.
     */
    private function buildQuery(array $data): string
    {
        $parts = array();
        foreach ($data as $key => $value) {
            if (null === $value) {
                continue;
            }

            if (\is_array($value)) {
                foreach ($value as $v) {
                    $parts[] = $this->encode($key, $v);
                }

                continue;
            }

            $parts[] = $this->encode($key, $value);
        }

        return implode('&', $parts);
    }

    private function encode($key, $value): string
    {
        // All Mercure's keys are safe, so don't need to be encoded, but it's not a generic solution
        return sprintf('%s=%s', $key, urlencode($value));
    }

    private function publish(string $url, string $jwt, string $postData): string
    {
        $result = @file_get_contents($this->hubUrl, false, stream_context_create(array('http' => array(
            'method' => 'POST',
            'header' => "Content-type: application/x-www-form-urlencoded\r\nAuthorization: Bearer $jwt",
            'content' => $postData,
        ))));

        if (false === $result) {
            throw new \RuntimeException(sprintf('Unable to publish the update to the Mercure hub: %s', error_get_last()['message'] ?? 'unknown error'));
        }

        return $result;
    }

    /**
     * Regex ported from Windows Azure Active Directory IdentityModel Extensions for .Net.
     *
     * @throws \InvalidArgumentException
     *
     * @license MIT
     * @copyright Copyright (c) Microsoft Corporation
     *
     * @see https://github.com/AzureAD/azure-activedirectory-identitymodel-extensions-for-dotnet/blob/6e7a53e241e4566998d3bf365f03acd0da699a31/src/Microsoft.IdentityModel.JsonWebTokens/JwtConstants.cs#L58
     */
    private function validateJwt(string $jwt): void
    {
        if (!preg_match('/^[A-Za-z0-9-_]+\.[A-Za-z0-9-_]+\.[A-Za-z0-9-_]*$/', $jwt)) {
            throw new \InvalidArgumentException('The provided JWT is not valid');
        }
    }
}
