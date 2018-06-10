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

namespace Symfony\Component\HttpKernel\Tests\HttpCache;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpCache\ResponseCacheStrategy;

class ResponseCacheStrategyTest extends TestCase
{
    public function testMinimumSharedMaxAgeWins()
    {
        $cacheStrategy = new ResponseCacheStrategy();

        $response1 = new Response();
        $response1->setSharedMaxAge(60);
        $cacheStrategy->add($response1);

        $response2 = new Response();
        $response2->setSharedMaxAge(3600);
        $cacheStrategy->add($response2);

        $response = new Response();
        $response->setSharedMaxAge(86400);
        $cacheStrategy->update($response);

        $this->assertSame('60', $response->headers->getCacheControlDirective('s-maxage'));
    }

    public function testSharedMaxAgeNotSetIfNotSetInAnyEmbeddedRequest()
    {
        $cacheStrategy = new ResponseCacheStrategy();

        $response1 = new Response();
        $response1->setSharedMaxAge(60);
        $cacheStrategy->add($response1);

        $response2 = new Response();
        $cacheStrategy->add($response2);

        $response = new Response();
        $response->setSharedMaxAge(86400);
        $cacheStrategy->update($response);

        $this->assertFalse($response->headers->hasCacheControlDirective('s-maxage'));
    }

    public function testSharedMaxAgeNotSetIfNotSetInMasterRequest()
    {
        $cacheStrategy = new ResponseCacheStrategy();

        $response1 = new Response();
        $response1->setSharedMaxAge(60);
        $cacheStrategy->add($response1);

        $response2 = new Response();
        $response2->setSharedMaxAge(3600);
        $cacheStrategy->add($response2);

        $response = new Response();
        $cacheStrategy->update($response);

        $this->assertFalse($response->headers->hasCacheControlDirective('s-maxage'));
    }

    public function testMasterResponseNotCacheableWhenEmbeddedResponseRequiresValidation()
    {
        $cacheStrategy = new ResponseCacheStrategy();

        $embeddedResponse = new Response();
        $embeddedResponse->setLastModified(new \DateTime());
        $cacheStrategy->add($embeddedResponse);

        $masterResponse = new Response();
        $masterResponse->setSharedMaxAge(3600);
        $cacheStrategy->update($masterResponse);

        $this->assertTrue($masterResponse->headers->hasCacheControlDirective('no-cache'));
        $this->assertTrue($masterResponse->headers->hasCacheControlDirective('must-revalidate'));
        $this->assertFalse($masterResponse->isFresh());
    }

    public function testValidationOnMasterResponseIsNotPossibleWhenItContainsEmbeddedResponses()
    {
        $cacheStrategy = new ResponseCacheStrategy();

        // This master response uses the "validation" model
        $masterResponse = new Response();
        $masterResponse->setLastModified(new \DateTime());
        $masterResponse->setEtag('foo');

        // Embedded response uses "expiry" model
        $embeddedResponse = new Response();
        $masterResponse->setSharedMaxAge(3600);
        $cacheStrategy->add($embeddedResponse);

        $cacheStrategy->update($masterResponse);

        $this->assertFalse($masterResponse->isValidateable());
        $this->assertFalse($masterResponse->headers->has('Last-Modified'));
        $this->assertFalse($masterResponse->headers->has('ETag'));
        $this->assertTrue($masterResponse->headers->hasCacheControlDirective('no-cache'));
        $this->assertTrue($masterResponse->headers->hasCacheControlDirective('must-revalidate'));
    }

    public function testMasterResponseWithValidationIsUnchangedWhenThereIsNoEmbeddedResponse()
    {
        $cacheStrategy = new ResponseCacheStrategy();

        $masterResponse = new Response();
        $masterResponse->setLastModified(new \DateTime());
        $cacheStrategy->update($masterResponse);

        $this->assertTrue($masterResponse->isValidateable());
    }

    public function testMasterResponseWithExpirationIsUnchangedWhenThereIsNoEmbeddedResponse()
    {
        $cacheStrategy = new ResponseCacheStrategy();

        $masterResponse = new Response();
        $masterResponse->setSharedMaxAge(3600);
        $cacheStrategy->update($masterResponse);

        $this->assertTrue($masterResponse->isFresh());
    }

    public function testMasterResponseIsNotCacheableWhenEmbeddedResponseIsNotCacheable()
    {
        $cacheStrategy = new ResponseCacheStrategy();

        $masterResponse = new Response();
        $masterResponse->setSharedMaxAge(3600); // Public, cacheable

        /* This response has no validation or expiration information.
           That makes it uncacheable, it is always stale.
           (It does *not* make this private, though.) */
        $embeddedResponse = new Response();
        $this->assertFalse($embeddedResponse->isFresh()); // not fresh, as no lifetime is provided

        $cacheStrategy->add($embeddedResponse);
        $cacheStrategy->update($masterResponse);

        $this->assertTrue($masterResponse->headers->hasCacheControlDirective('no-cache'));
        $this->assertTrue($masterResponse->headers->hasCacheControlDirective('must-revalidate'));
        $this->assertFalse($masterResponse->isFresh());
    }

    public function testEmbeddingPrivateResponseMakesMainResponsePrivate()
    {
        $cacheStrategy = new ResponseCacheStrategy();

        $masterResponse = new Response();
        $masterResponse->setSharedMaxAge(3600); // public, cacheable

        // The embedded response might for example contain per-user data that remains valid for 60 seconds
        $embeddedResponse = new Response();
        $embeddedResponse->setPrivate();
        $embeddedResponse->setMaxAge(60); // this would implicitly set "private" as well, but let's be explicit

        $cacheStrategy->add($embeddedResponse);
        $cacheStrategy->update($masterResponse);

        $this->assertTrue($masterResponse->headers->hasCacheControlDirective('private'));
        $this->assertFalse($masterResponse->headers->hasCacheControlDirective('public'));
    }

    public function testEmbeddingPublicResponseDoesNotMakeMainResponsePublic()
    {
        $cacheStrategy = new ResponseCacheStrategy();

        $masterResponse = new Response();
        $masterResponse->setPrivate(); // this is the default, but let's be explicit
        $masterResponse->setMaxAge(100);

        $embeddedResponse = new Response();
        $embeddedResponse->setPublic();
        $embeddedResponse->setSharedMaxAge(100);

        $cacheStrategy->add($embeddedResponse);
        $cacheStrategy->update($masterResponse);

        $this->assertTrue($masterResponse->headers->hasCacheControlDirective('private'));
        $this->assertFalse($masterResponse->headers->hasCacheControlDirective('public'));
    }

    public function testResponseIsExiprableWhenEmbeddedResponseCombinesExpiryAndValidation()
    {
        /* When "expiration wins over validation" (https://symfony.com/doc/current/http_cache/validation.html)
         * and both the main and embedded response provide s-maxage, then the more restricting value of both
         * should be fine, regardless of whether the embedded response can be validated later on or must be
         * completely regenerated.
         */
        $cacheStrategy = new ResponseCacheStrategy();

        $masterResponse = new Response();
        $masterResponse->setSharedMaxAge(3600);

        $embeddedResponse = new Response();
        $embeddedResponse->setSharedMaxAge(60);
        $embeddedResponse->setEtag('foo');

        $cacheStrategy->add($embeddedResponse);
        $cacheStrategy->update($masterResponse);

        $this->assertSame('60', $masterResponse->headers->getCacheControlDirective('s-maxage'));
    }

    public function testResponseIsExpirableButNotValidateableWhenMasterResponseCombinesExpirationAndValidation()
    {
        $cacheStrategy = new ResponseCacheStrategy();

        $masterResponse = new Response();
        $masterResponse->setSharedMaxAge(3600);
        $masterResponse->setEtag('foo');
        $masterResponse->setLastModified(new \DateTime());

        $embeddedResponse = new Response();
        $embeddedResponse->setSharedMaxAge(60);

        $cacheStrategy->add($embeddedResponse);
        $cacheStrategy->update($masterResponse);

        $this->assertSame('60', $masterResponse->headers->getCacheControlDirective('s-maxage'));
        $this->assertFalse($masterResponse->isValidateable());
    }
}
