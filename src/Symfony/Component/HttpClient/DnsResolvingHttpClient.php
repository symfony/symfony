<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient;

use Symfony\Component\HttpClient\Internal\FollowRedirectsTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Decorator that resolves host names using a custom resolver before delegating to the transport.
 *
 * The resolver is called for the requested host and for the host of every followed redirect. When it
 * returns an IP address, the result is injected into the "resolve" option so that the transport connects
 * to that IP without performing its own DNS resolution. When it returns null, the transport's default
 * DNS resolution is used. Hosts that are already in the "resolve" option or that are IP addresses are
 * not passed to it.
 *
 * Note that using this decorator opts out of the asynchronous and cached DNS resolution that the curl
 * and amphp transports provide; the resolver is responsible for any caching it needs.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class DnsResolvingHttpClient implements HttpClientInterface, ResetInterface
{
    use AsyncDecoratorTrait;
    use FollowRedirectsTrait;
    use HttpClientTrait;

    private array $defaultOptions = self::OPTIONS_DEFAULTS;

    /** @var callable(string): ?string */
    private $resolver;

    /**
     * @param callable(string $host): ?string $resolver Returns the IP address the given host name should resolve to,
     *                                                  or null to let the transport perform its default DNS resolution
     */
    public function __construct(
        private HttpClientInterface $client,
        callable $resolver,
    ) {
        $this->resolver = $resolver;
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        [$url, $options] = self::prepareRequest($method, $url, $options, $this->defaultOptions, true);

        $host = parse_url($url['authority'], \PHP_URL_HOST);
        $url = implode('', $url);

        $resolver = $this->resolver;
        $resolve = static function (string $host, string $url, array &$options) use ($resolver): void {
            if (isset($options['resolve'][$host]) || false !== filter_var(trim($host, '[]'), \FILTER_VALIDATE_IP)) {
                return;
            }

            if (null !== $ip = $resolver($host)) {
                $options['resolve'][$host] = $ip;
            }
        };

        $resolve($host, $url, $options);

        return $this->followRedirects($method, $url, $host, $options, $resolve);
    }

    public function withOptions(array $options): static
    {
        $clone = clone $this;
        $clone->client = $this->client->withOptions($options);
        $clone->defaultOptions = self::mergeDefaultOptions($options, $this->defaultOptions);

        return $clone;
    }

    public function reset(): void
    {
        if ($this->resolver instanceof ResetInterface) {
            $this->resolver->reset();
        }

        if ($this->client instanceof ResetInterface) {
            $this->client->reset();
        }
    }
}
