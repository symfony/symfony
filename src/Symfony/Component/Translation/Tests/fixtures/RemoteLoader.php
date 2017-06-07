<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Fixtures;

use Symfony\Component\Translation\Loader\RemoteLoaderInterface;

class RemoteLoader implements RemoteLoaderInterface
{
    private $source;

    public function __construct(array $source)
    {
        $this->source = $source;
    }

    public function getRemoteResources()
    {
        return array_keys($this->source);
    }

    public function getLocalesForResource($resource)
    {
        if (array_key_exists($resource, $this->source)) {
            return array_keys($this->source[$resource]);
        }

        return array();
    }

    public function getDomainsForLocale($resource, $locale)
    {
        if (array_key_exists($resource, $this->source)) {
            if (array_key_exists($locale, $this->source[$resource])) {
                return array_keys($this->source[$resource][$locale]);
            }
        }

        return array();
    }

    public function load($resource, $locale, $domain = 'messages')
    {
        if (array_key_exists($resource, $this->source)) {
            if (array_key_exists($locale, $this->source[$resource])) {
                if (array_key_exists($domain, $this->source[$resource][$locale])) {
                    return $this->source[$resource][$locale][$domain];
                }
            }
        }

        return array();
    }
}
