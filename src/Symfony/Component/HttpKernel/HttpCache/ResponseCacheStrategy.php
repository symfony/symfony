<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This code is partially based on the Rack-Cache library by Ryan Tomayko,
 * which is released under the MIT license.
 * (based on commit 02d2b48d75bcb63cf1c0c7149c077ad256542801)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\HttpCache;

use Symfony\Component\HttpFoundation\Response;

/**
 * ResponseCacheStrategy knows how to compute the Response cache HTTP header
 * based on the different response cache headers.
 *
 * This implementation changes the master response TTL to the smallest TTL received
 * or force validation if one of the surrogates has validation cache strategy.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ResponseCacheStrategy implements ResponseCacheStrategyInterface
{
    private $cacheable = true;
    private $embeddedResponses = 0;
    private $ttls = [];
    private $maxAges = [];
    private $isNotCacheableResponseEmbedded = false;

    /**
     * {@inheritdoc}
     */
    public function add(Response $response)
    {
        if (!$response->isFresh() || !$response->isCacheable()) {
            $this->cacheable = false;
        } else {
            $maxAge = $response->getMaxAge();
            $this->ttls[] = $response->getTtl();
            $this->maxAges[] = $maxAge;

            if (null === $maxAge) {
                $this->isNotCacheableResponseEmbedded = true;
            }
        }

        ++$this->embeddedResponses;
    }

    /**
     * {@inheritdoc}
     */
    public function update(Response $response)
    {
        // if we have no embedded Response, do nothing
        if (0 === $this->embeddedResponses) {
            return;
        }

        // Remove validation related headers in order to avoid browsers using
        // their own cache, because some of the response content comes from
        // at least one embedded response (which likely has a different caching strategy).
        if ($response->isValidateable()) {
            $response->setEtag(null);
            $response->setLastModified(null);
        }

        if (!$response->isFresh() || !$response->isCacheable()) {
            $this->cacheable = false;
        }

        if (!$this->cacheable) {
            $response->headers->set('Cache-Control', 'no-cache, must-revalidate');

            return;
        }

        $this->ttls[] = $response->getTtl();
        $this->maxAges[] = $response->getMaxAge();

        if ($this->isNotCacheableResponseEmbedded) {
            $response->headers->removeCacheControlDirective('s-maxage');
        } elseif (null !== $maxAge = min($this->maxAges)) {
            $response->setSharedMaxAge($maxAge);
            $response->headers->set('Age', $maxAge - min($this->ttls));
        }
        $response->setMaxAge(0);
    }
}
