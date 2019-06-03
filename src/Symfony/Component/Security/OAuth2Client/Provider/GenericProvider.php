<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\OAuth2Client\Provider;

use Psr\Log\LoggerInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\OAuth2Client\Exception\InvalidRequestException;
use Symfony\Component\Security\OAuth2Client\Exception\InvalidUrlException;
use Symfony\Component\Security\OAuth2Client\Loader\ClientProfileLoader;
use Symfony\Component\Security\OAuth2Client\Token\RefreshToken;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
abstract class GenericProvider implements ProviderInterface
{
    private const DEFAULT_OPTIONS = [
        'client_id' => ['null', 'string'],
        'client_secret' => ['null', 'string'],
        'redirect_uri' => ['null', 'string'],
        'authorization_url' => ['null', 'string'],
        'access_token_url' => ['null', 'string'],
        'user_details_url' => ['null', 'string'],
    ];

    private const ERROR_OPTIONS = [
        'error',
        'error_description',
        'error_uri',
    ];

    private const URL_OPTIONS = [
        'redirect_uri',
        'authorization_url',
        'access_token_url',
        'userDetails_url',
    ];

    protected $client;
    protected $logger;
    protected $options = [];

    public function __construct(HttpClientInterface $client, array $options = [], LoggerInterface $logger = null)
    {
        $resolver = new OptionsResolver();
        $this->defineOptions($resolver);

        $this->options = $resolver->resolve($options);

        $this->validateUrls($this->options);

        $this->client = $client;
        $this->logger = $logger;
    }

    private function defineOptions(OptionsResolver $resolver): void
    {
        foreach (self::DEFAULT_OPTIONS as $option => $optionType) {
            $resolver->setRequired($option);
            $resolver->setAllowedTypes($option, $optionType);
        }
    }

    private function validateUrls(array $urls)
    {
        foreach ($urls as $key => $url) {
            if (\in_array($key, self::URL_OPTIONS)) {
                if (!preg_match('~^{http|https}|[\w+.-]+://~', $url)) {
                    throw new InvalidUrlException(\sprintf('The given URL %s isn\'t a valid one.', $url));
                }
            }
        }
    }

    /**
     * Allow to add extra arguments to the actual request.
     *
     * @param array $defaultArguments the required arguments for the actual request (based on the RFC)
     * @param array $extraArguments   the extra arguments that can be optionals/recommended
     *
     * @return array the final arguments sent to the request
     */
    protected function mergeRequestArguments(array $defaultArguments, array $extraArguments = []): array
    {
        if (0 < \count($extraArguments)) {
            $finalArguments = array_unique(array_merge($defaultArguments, $extraArguments));
        }

        return $finalArguments ?? $defaultArguments;
    }

    protected function checkResponseIsCacheable(ResponseInterface $response): void
    {
        $headers = $response->getInfo('response_headers');

        if (isset($headers['Cache-Control']) && 'no-store' !== $headers['Cache-Control']) {
            if ($this->logger) {
                $this->logger->warning('This response is marked as cacheable.');
            }
        }
    }

    public function parseResponse(ResponseInterface $response): array
    {
        $content = $response->getContent();

        $parsedUrl = parse_url($content, PHP_URL_QUERY);
        parse_str($parsedUrl, $matches);

        foreach ($matches as $keys => $value) {
            if (\in_array($keys, self::ERROR_OPTIONS)) {
                throw new InvalidRequestException(
                    \sprintf('It seems that the request encounter an error %s', $value)
                );
            }
        }

        return $matches;
    }

    /**
     * {@inheritdoc}
     */
    public function refreshToken(string $refreshToken, string $scope = null, array $headers = [], string $method = 'GET'): RefreshToken
    {
        $query = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ];

        if (null !== $scope) {
            $query['scope'] = $scope;
        } else {
            if ($this->logger) {
                $this->logger->info('The scope isn\'t defined, the response can vary from the expected behaviour.');
            }
        }

        $defaultHeaders = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];

        $finalHeaders = $this->mergeRequestArguments($defaultHeaders, $headers);

        $response = $this->client->request($method, $this->options['access_token_url'], [
            'headers' => $finalHeaders,
            'query' => $query,
        ]);

        $this->parseResponse($response);

        return new RefreshToken($response->toArray());
    }

    public function prepareClientProfileLoader(): ClientProfileLoader
    {
        return new ClientProfileLoader($this->client, $this->options['user_details_url']);
    }
}
