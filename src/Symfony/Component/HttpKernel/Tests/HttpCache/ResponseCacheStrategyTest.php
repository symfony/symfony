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

    /**
     * @dataProvider cacheControlMergingProvider
     */
    public function testCacheControlMerging(array $expects, array $master, array $surrogates)
    {
        $cacheStrategy = new ResponseCacheStrategy();
        $buildResponse = function ($config) {
            $response = new Response();

            foreach ($config as $key => $value) {
                switch ($key) {
                    case 'max-age':
                        $response->setMaxAge($value);
                        break;

                    case 's-maxage':
                        $response->setSharedMaxAge($value);
                        break;

                    case 'private':
                        $response->setPrivate();
                        break;

                    case 'public':
                        $response->setPublic();
                        break;

                    default:
                        $response->headers->addCacheControlDirective($key, $value);
                }
            }

            return $response;
        };

        foreach ($surrogates as $config) {
            $cacheStrategy->add($buildResponse($config));
        }

        $response = $buildResponse($master);
        $cacheStrategy->update($response);

        foreach ($expects as $key => $value) {
            if (true === $value) {
                $this->assertTrue($response->headers->hasCacheControlDirective($key), sprintf('Cache-Control header must have "%s" flag', $key));
            } elseif (false === $value) {
                $this->assertFalse($response->headers->hasCacheControlDirective($key), sprintf('Cache-Control header must NOT have "%s" flag', $key));
            } else {
                $this->assertEquals($value, $response->headers->getCacheControlDirective($key), sprintf('Cache-Control flag "%s" should be "%s"', $key, $value));
            }
        }
    }

    public function cacheControlMergingProvider()
    {
        yield 'result is public if all responses are public' => array(
            array('private' => false, 'public' => true),
            array('public' => true),
            array(
                array('public' => true),
            ),
        );

        yield 'result is private by default' => array(
            array('private' => true, 'public' => false),
            array('public' => true),
            array(
                array(),
            ),
        );

        yield 'combines public and private responses' => array(
            array('must-revalidate' => false, 'private' => true, 'public' => false),
            array('public' => true),
            array(
                array('private' => true),
            ),
        );

        yield 'inherits no-cache from surrogates' => array(
            array('no-cache' => true, 'public' => false),
            array('public' => true),
            array(
                array('no-cache' => true),
            ),
        );

        yield 'inherits no-store from surrogate' => array(
            array('no-store' => true, 'public' => false),
            array('public' => true),
            array(
                array('no-store' => true),
            ),
        );

        yield 'resolve to lowest possible max-age' => array(
            array('public' => false, 'private' => true, 's-maxage' => false, 'max-age' => 60),
            array('public' => true, 'max-age' => 3600),
            array(
                array('private' => true, 'max-age' => 60),
            ),
        );

        yield 'resolves multiple max-age' => array(
            array('public' => false, 'private' => true, 's-maxage' => false, 'max-age' => 60),
            array('private' => true, 'max-age' => 100),
            array(
                array('private' => true, 'max-age' => 3600),
                array('public' => true, 'max-age' => 60, 's-maxage' => 60),
                array('private' => true, 'max-age' => 60),
            ),
        );

        yield 'merge max-age and s-maxage' => array(
            array('public' => true, 's-maxage' => 60, 'max-age' => null),
            array('public' => true, 's-maxage' => 3600),
            array(
                array('public' => true, 'max-age' => 60),
            ),
        );

        yield 'result is private when combining private responses' => array(
            array('no-cache' => false, 'must-revalidate' => false, 'private' => true),
            array('s-maxage' => 60, 'private' => true),
            array(
                array('s-maxage' => 60, 'private' => true),
            ),
        );

        yield 'result can have s-maxage and max-age' => array(
            array('public' => true, 'private' => false, 's-maxage' => 60, 'max-age' => 30),
            array('s-maxage' => 100, 'max-age' => 2000),
            array(
                array('s-maxage' => 1000, 'max-age' => 30),
                array('s-maxage' => 500, 'max-age' => 500),
                array('s-maxage' => 60, 'max-age' => 1000),
            ),
        );
    }
}
