<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Attribute;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireInline;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class AutowireHttpClient extends AutowireInline
{
    public function __construct(?string $baseUri = null, array $options = [], string $client = 'http_client')
    {
        Autowire::__construct([new Reference($client), $baseUri, $options]);
    }

    public function buildDefinition(mixed $value, ?string $type, \ReflectionParameter $parameter): Definition
    {
        [$client, $baseUri, $options] = $value;

        if (null === $baseUri) {
            return new Definition(HttpClientInterface::class, [$options])
                ->setFactory([$client, 'withOptions']);
        }

        return new Definition(ScopingHttpClient::class, $value)
            ->setFactory([ScopingHttpClient::class, 'forBaseUri']);
    }
}
