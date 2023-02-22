<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
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

    public function testSharedMaxAgeNotSetIfNotSetInMainRequest()
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

    public function testMainResponseNotCacheableWhenEmbeddedResponseRequiresValidation()
    {
        $cacheStrategy = new ResponseCacheStrategy();

        $embeddedResponse = new Response();
        $embeddedResponse->setLastModified(new \DateTime());
        $cacheStrategy->add($embeddedResponse);

        $mainResponse = new Response();
        $mainResponse->setSharedMaxAge(3600);
        $cacheStrategy->update($mainResponse);

        $this->assertTrue($mainResponse->headers->hasCacheControlDirective('no-cache'));
        $this->assertTrue($mainResponse->headers->hasCacheControlDirective('must-revalidate'));
        $this->assertFalse($mainResponse->isFresh());
    }

    public function testValidationOnMainResponseIsNotPossibleWhenItContainsEmbeddedResponses()
    {
        $cacheStrategy = new ResponseCacheStrategy();

        // This main response uses the "validation" model
        $mainResponse = new Response();
        $mainResponse->setLastModified(new \DateTime());
        $mainResponse->setEtag('foo');

        // Embedded response uses "expiry" model
        $embeddedResponse = new Response();
        $mainResponse->setSharedMaxAge(3600);
        $cacheStrategy->add($embeddedResponse);

        $cacheStrategy->update($mainResponse);

        $this->assertFalse($mainResponse->isValidateable());
        $this->assertFalse($mainResponse->headers->has('Last-Modified'));
        $this->assertFalse($mainResponse->headers->has('ETag'));
        $this->assertTrue($mainResponse->headers->hasCacheControlDirective('no-cache'));
        $this->assertTrue($mainResponse->headers->hasCacheControlDirective('must-revalidate'));
    }

    public function testMainResponseWithValidationIsUnchangedWhenThereIsNoEmbeddedResponse()
    {
        $cacheStrategy = new ResponseCacheStrategy();

        $mainResponse = new Response();
        $mainResponse->setLastModified(new \DateTime());
        $cacheStrategy->update($mainResponse);

        $this->assertTrue($mainResponse->isValidateable());
    }

    public function testMainResponseWithExpirationIsUnchangedWhenThereIsNoEmbeddedResponse()
    {
        $cacheStrategy = new ResponseCacheStrategy();

        $mainResponse = new Response();
        $mainResponse->setSharedMaxAge(3600);
        $cacheStrategy->update($mainResponse);

        $this->assertTrue($mainResponse->isFresh());
    }

    public function testLastModifiedIsMergedWithEmbeddedResponse()
    {
        $cacheStrategy = new ResponseCacheStrategy();

        $embeddedDate = new \DateTime('-1 hour');

        // This master response uses the "validation" model
        $masterResponse = new Response();
        $masterResponse->setLastModified(new \DateTime('-2 hour'));
        $masterResponse->setEtag('foo');

        // Embedded response uses "expiry" model
        $embeddedResponse = new Response();
        $embeddedResponse->setLastModified($embeddedDate);
        $cacheStrategy->add($embeddedResponse);

        $cacheStrategy->update($masterResponse);

        $this->assertTrue($masterResponse->isValidateable());
        $this->assertSame($embeddedDate->getTimestamp(), $masterResponse->getLastModified()->getTimestamp());
    }

    public function testMainResponseIsNotCacheableWhenEmbeddedResponseIsNotCacheable()
    {
        $cacheStrategy = new ResponseCacheStrategy();

        $mainResponse = new Response();
        $mainResponse->setSharedMaxAge(3600); // Public, cacheable

        /* This response has no validation or expiration information.
           That makes it uncacheable, it is always stale.
           (It does *not* make this private, though.) */
        $embeddedResponse = new Response();
        $this->assertFalse($embeddedResponse->isFresh()); // not fresh, as no lifetime is provided

        $cacheStrategy->add($embeddedResponse);
        $cacheStrategy->update($mainResponse);

        $this->assertTrue($mainResponse->headers->hasCacheControlDirective('no-cache'));
        $this->assertTrue($mainResponse->headers->hasCacheControlDirective('must-revalidate'));
        $this->assertFalse($mainResponse->isFresh());
    }

    public function testEmbeddingPrivateResponseMakesMainResponsePrivate()
    {
        $cacheStrategy = new ResponseCacheStrategy();

        $mainResponse = new Response();
        $mainResponse->setSharedMaxAge(3600); // public, cacheable

        // The embedded response might for example contain per-user data that remains valid for 60 seconds
        $embeddedResponse = new Response();
        $embeddedResponse->setPrivate();
        $embeddedResponse->setMaxAge(60); // this would implicitly set "private" as well, but let's be explicit

        $cacheStrategy->add($embeddedResponse);
        $cacheStrategy->update($mainResponse);

        $this->assertTrue($mainResponse->headers->hasCacheControlDirective('private'));
        $this->assertFalse($mainResponse->headers->hasCacheControlDirective('public'));
    }

    public function testEmbeddingPublicResponseDoesNotMakeMainResponsePublic()
    {
        $cacheStrategy = new ResponseCacheStrategy();

        $mainResponse = new Response();
        $mainResponse->setPrivate(); // this is the default, but let's be explicit
        $mainResponse->setMaxAge(100);

        $embeddedResponse = new Response();
        $embeddedResponse->setPublic();
        $embeddedResponse->setSharedMaxAge(100);

        $cacheStrategy->add($embeddedResponse);
        $cacheStrategy->update($mainResponse);

        $this->assertTrue($mainResponse->headers->hasCacheControlDirective('private'));
        $this->assertFalse($mainResponse->headers->hasCacheControlDirective('public'));
    }

    public function testResponseIsExiprableWhenEmbeddedResponseCombinesExpiryAndValidation()
    {
        /* When "expiration wins over validation" (https://symfony.com/doc/current/http_cache/validation.html)
         * and both the main and embedded response provide s-maxage, then the more restricting value of both
         * should be fine, regardless of whether the embedded response can be validated later on or must be
         * completely regenerated.
         */
        $cacheStrategy = new ResponseCacheStrategy();

        $mainResponse = new Response();
        $mainResponse->setSharedMaxAge(3600);

        $embeddedResponse = new Response();
        $embeddedResponse->setSharedMaxAge(60);
        $embeddedResponse->setEtag('foo');

        $cacheStrategy->add($embeddedResponse);
        $cacheStrategy->update($mainResponse);

        $this->assertEqualsWithDelta(60, (int) $mainResponse->headers->getCacheControlDirective('s-maxage'), 1);
    }

    public function testResponseIsExpirableButNotValidateableWhenMainResponseCombinesExpirationAndValidation()
    {
        $cacheStrategy = new ResponseCacheStrategy();

        $mainResponse = new Response();
        $mainResponse->setSharedMaxAge(3600);
        $mainResponse->setEtag('foo');
        $mainResponse->setLastModified(new \DateTime());

        $embeddedResponse = new Response();
        $embeddedResponse->setSharedMaxAge(60);

        $cacheStrategy->add($embeddedResponse);
        $cacheStrategy->update($mainResponse);

        $this->assertSame('60', $mainResponse->headers->getCacheControlDirective('s-maxage'));
        $this->assertFalse($mainResponse->isValidateable());
    }

    /**
     * @group time-sensitive
     *
     * @dataProvider cacheControlMergingProvider
     */
    public function testCacheControlMerging(array $expects, array $master, array $surrogates)
    {
        $cacheStrategy = new ResponseCacheStrategy();
        $buildResponse = function ($config) {
            $response = new Response();

            foreach ($config as $key => $value) {
                switch ($key) {
                    case 'age':
                        $response->headers->set('Age', $value);
                        break;

                    case 'expires':
                        $expires = clone $response->getDate();
                        $expires->modify('+'.$value.' seconds');
                        $response->setExpires($expires);
                        break;

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
            if ('expires' === $key) {
                $this->assertSame($value, $response->getExpires()->format('U') - $response->getDate()->format('U'));
            } elseif ('age' === $key) {
                $this->assertSame($value, $response->getAge());
            } elseif (true === $value) {
                $this->assertTrue($response->headers->hasCacheControlDirective($key), sprintf('Cache-Control header must have "%s" flag', $key));
            } elseif (false === $value) {
                $this->assertFalse(
                    $response->headers->hasCacheControlDirective($key),
                    sprintf('Cache-Control header must NOT have "%s" flag', $key)
                );
            } else {
                $this->assertSame($value, $response->headers->getCacheControlDirective($key), sprintf('Cache-Control flag "%s" should be "%s"', $key, $value));
            }
        }
    }

    public static function cacheControlMergingProvider()
    {
        yield 'result is public if all responses are public' => [
            ['private' => false, 'public' => true],
            ['public' => true],
            [
                ['public' => true],
            ],
        ];

        yield 'result is private by default' => [
            ['private' => true, 'public' => false],
            ['public' => true],
            [
                [],
            ],
        ];

        yield 'combines public and private responses' => [
            ['must-revalidate' => false, 'private' => true, 'public' => false],
            ['public' => true],
            [
                ['private' => true],
            ],
        ];

        yield 'inherits no-cache from surrogates' => [
            ['no-cache' => true, 'public' => false],
            ['public' => true],
            [
                ['no-cache' => true],
            ],
        ];

        yield 'inherits no-store from surrogate' => [
            ['no-store' => true, 'public' => false],
            ['public' => true],
            [
                ['no-store' => true],
            ],
        ];

        yield 'resolve to lowest possible max-age' => [
            ['public' => false, 'private' => true, 's-maxage' => false, 'max-age' => '60'],
            ['public' => true, 'max-age' => 3600],
            [
                ['private' => true, 'max-age' => 60],
            ],
        ];

        yield 'resolves multiple max-age' => [
            ['public' => false, 'private' => true, 's-maxage' => false, 'max-age' => '60'],
            ['private' => true, 'max-age' => 100],
            [
                ['private' => true, 'max-age' => 3600],
                ['public' => true, 'max-age' => 60, 's-maxage' => 60],
                ['private' => true, 'max-age' => 60],
            ],
        ];

        yield 'merge max-age and s-maxage' => [
            ['public' => true, 'max-age' => '60'],
            ['public' => true, 's-maxage' => 3600],
            [
                ['public' => true, 'max-age' => 60],
            ],
        ];

        yield 's-maxage may be set to 0' => [
            ['public' => true, 's-maxage' => '0', 'max-age' => null],
            ['public' => true, 's-maxage' => '0'],
            [
                ['public' => true, 's-maxage' => '60'],
            ],
        ];

        yield 's-maxage may be set to 0, and works independently from maxage' => [
            ['public' => true, 's-maxage' => '0', 'max-age' => '30'],
            ['public' => true, 's-maxage' => '0', 'max-age' => '30'],
            [
                ['public' => true, 'max-age' => '60'],
            ],
        ];

        yield 'public subresponse without lifetime does not remove lifetime for main response' => [
            ['public' => true, 's-maxage' => '30', 'max-age' => null],
            ['public' => true, 's-maxage' => '30'],
            [
                ['public' => true],
            ],
        ];

        yield 'lifetime for subresponse is kept when main response has no lifetime' => [
            ['public' => true, 'max-age' => '30'],
            ['public' => true],
            [
                ['public' => true, 'max-age' => '30'],
            ],
        ];

        yield 's-maxage on the subresponse implies public, so the result is public as well' => [
            ['public' => true, 'max-age' => '10', 's-maxage' => null],
            ['public' => true, 'max-age' => '10'],
            [
                ['max-age' => '30', 's-maxage' => '20'],
            ],
        ];

        yield 'result is private when combining private responses' => [
            ['no-cache' => false, 'must-revalidate' => false, 'private' => true],
            ['s-maxage' => 60, 'private' => true],
            [
                ['s-maxage' => 60, 'private' => true],
            ],
        ];

        yield 'result can have s-maxage and max-age' => [
            ['public' => true, 'private' => false, 's-maxage' => '60', 'max-age' => '30'],
            ['s-maxage' => 100, 'max-age' => 2000],
            [
                ['s-maxage' => 1000, 'max-age' => 30],
                ['s-maxage' => 500, 'max-age' => 500],
                ['s-maxage' => 60, 'max-age' => 1000],
            ],
        ];

        yield 'does not set headers without value' => [
            ['max-age' => null, 's-maxage' => null, 'public' => null],
            ['private' => true],
            [
                ['private' => true],
            ],
        ];

        yield 'max-age 0 is sent to the client' => [
            ['private' => true, 'max-age' => '0'],
            ['max-age' => 0, 'private' => true],
            [
                ['max-age' => 60, 'private' => true],
            ],
        ];

        yield 'max-age is relative to age' => [
            ['max-age' => '240', 'age' => 60],
            ['max-age' => 180],
            [
                ['max-age' => 600, 'age' => 60],
            ],
        ];

        yield 'retains lowest age of all responses' => [
            ['max-age' => '160', 'age' => 60],
            ['max-age' => 600, 'age' => 60],
            [
                ['max-age' => 120, 'age' => 20],
            ],
        ];

        yield 'max-age can be less than age, essentially expiring the response' => [
            ['age' => 120, 'max-age' => '90'],
            ['max-age' => 90, 'age' => 120],
            [
                ['max-age' => 120, 'age' => 60],
            ],
        ];

        yield 'max-age is 0 regardless of age' => [
            ['max-age' => '0'],
            ['max-age' => 60],
            [
                ['max-age' => 0, 'age' => 60],
            ],
        ];

        yield 'max-age is not negative' => [
            ['max-age' => '0'],
            ['max-age' => 0],
            [
                ['max-age' => 0, 'age' => 60],
            ],
        ];

        yield 'calculates lowest Expires header' => [
            ['expires' => 60],
            ['expires' => 60],
            [
                ['expires' => 120],
            ],
        ];

        yield 'calculates Expires header relative to age' => [
            ['expires' => 210, 'age' => 120],
            ['expires' => 90],
            [
                ['expires' => 600, 'age' => '120'],
            ],
        ];
    }
}
