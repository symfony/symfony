<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests\Response;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Internal\ClientState;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\AsyncResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

class AsyncResponseTest extends TestCase
{
    /**
     * Regression test for a production crash: "A chunk passthru must yield an isFirst() chunk
     * before any content chunk", thrown from AsyncResponse::__destruct() for a response that was
     * created and discarded without ever being read.
     *
     * AsyncResponse::stream() guards against a content chunk arriving for a response that hasn't
     * yielded a real isFirst() chunk yet, by checking $r->yieldedState. But that guard used to
     * never get re-armed after AsyncContext::replaceRequest()/replaceResponse() swapped the
     * wrapped response without ever yielding anything for it -- which is exactly what happens
     * while a redirect or a retry is being followed: $r->yieldedState stays at
     * FIRST_CHUNK_YIELDED from the *previous*, swapped-out response.
     *
     * If the very next raw chunk delivered for the *new*, swapped-in response is not its own real
     * isFirst() chunk but a stray content chunk instead, the stale guard let it slip all the way
     * into passthruStream(), which crashed with the confusing "isFirst() chunk before any content
     * chunk" LogicException. In production (CurlHttpClient), such a stray chunk can come from a
     * *different*, previously abandoned response: a response that is created and discarded
     * without ever being fully read is not always destructed immediately --
     * TransportResponseTrait::doDestruct() is a no-op once the status code has been peeked at even
     * once, so cleanup relies entirely on the Canary finalizer, which can run arbitrarily late.
     * Meanwhile the underlying curl transfer keeps running in the background and queues its
     * remaining body bytes into the client's shared, id-keyed activity buffer. Because PHP reuses
     * the integer id of a CurlHandle object as soon as it is actually freed, a later, unrelated
     * response can end up assigned that very same id while the old, undrained chunks are still
     * sitting there -- and they get handed to the new response as if they were its own.
     *
     * The fix re-arms the guard (resets $innerR->yieldedState) as soon as a response swap is
     * detected, so the new response's very first chunk is validated again from scratch. This test
     * drives a real swap via AsyncContext::replaceResponse(), and seeds the shared activity buffer
     * with a stray chunk for the id the swapped-in response is about to receive -- reproducing,
     * deterministically and without any curl/GC timing, the exact corrupted state that used to
     * reach production. It asserts that the stray chunk is now rejected safely and early, with an
     * accurate error, instead of corrupting the response or crashing deep inside passthruStream().
     */
    public function testStrayChunkForSwappedResponseIsRejectedInsteadOfCorruptingTheResponse()
    {
        $idSequence = new \ReflectionProperty(MockResponse::class, 'idSequence');
        $base = $idSequence->getValue();

        $mainMulti = new \ReflectionProperty(MockResponse::class, 'mainMulti');
        if (!$mainMulti->isInitialized()) {
            $mainMulti->setValue(null, new ClientState());
        }

        // $mock1 below will be assigned id = $base + 1. The swap performed by $passthru further
        // down will request() a second response, which will be assigned id = $base + 2. Seed that
        // id with a stray content chunk *before* it's ever requested -- standing in for the
        // leftover body bytes of a previously abandoned, never-fully-read response whose id got
        // reused before its trailing activity was drained.
        $mainMulti->getValue()->handlesActivity[$base + 2] = ['stray content chunk from an unrelated, earlier response'];

        $client2 = new MockHttpClient([new MockResponse('second response body')]);

        // A passthru shaped like NoPrivateNetworkHttpClient's (and RetryableHttpClient's): on the
        // real isFirst chunk, decide to replace the response instead of yielding it -- exactly what
        // happens while following a redirect or a retry.
        $passthru = static function ($chunk, $context) use ($client2) {
            if (null !== $chunk->getError() || !$chunk->isFirst()) {
                yield $chunk;

                return;
            }

            $context->replaceResponse($client2->request('GET', 'http://example.com/second'));
        };

        $client1 = new MockHttpClient([new MockResponse('first response body')]);
        $response = new AsyncResponse($client1, 'GET', 'http://example.com/first', [], $passthru);

        // The stray chunk queued for the swapped-in response must be rejected with a clear error --
        // not silently attributed to it, and not by crashing deep inside passthruStream() with a
        // message that gives no hint about what actually went wrong.
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('is already consumed and cannot be managed by');

        $response->getContent();
    }

    /**
     * Same corrupted state as above, but for a response built *without* a passthru at all (e.g.
     * NoPrivateNetworkHttpClient::request() with max_redirects: 0, or a bare EventSourceHttpClient
     * response). Such a response takes AsyncResponse::stream()'s "fast path" (it calls
     * passthruStream() directly), which used to have no "not yet started" guard whatsoever: a
     * stray first chunk would crash straight into passthruStream() with the original, confusing
     * "isFirst() chunk before any content chunk" message. The fix hoists the guard so it runs for
     * every chunk regardless of whether $r has a passthru.
     */
    public function testStrayChunkForResponseWithoutPassthruIsRejectedInsteadOfCorruptingTheResponse()
    {
        $idSequence = new \ReflectionProperty(MockResponse::class, 'idSequence');
        $nextId = $idSequence->getValue() + 1;

        $mainMulti = new \ReflectionProperty(MockResponse::class, 'mainMulti');
        if (!$mainMulti->isInitialized()) {
            $mainMulti->setValue(null, new ClientState());
        }

        // Standing in for the leftover body bytes of a previously abandoned, never-fully-read
        // response whose id got reused before its trailing activity was drained.
        $mainMulti->getValue()->handlesActivity[$nextId] = ['stray content chunk from an unrelated, earlier response'];

        $response = new AsyncResponse(new MockHttpClient([new MockResponse('body')]), 'GET', 'http://example.com/', []);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('is already consumed and cannot be managed by');

        $response->getContent();
    }

    /**
     * Guards against a regression introduced while fixing the two tests above: the "not yet
     * started" guard must accept $r->yieldedState === LAST_CHUNK_YIELDED just as much as
     * FIRST_CHUNK_YIELDED. CommonResponseTrait::getContent() calls self::stream([$this]) again
     * even once the response is already fully buffered (as a "drain whatever is left" safety net,
     * see its "Chunks are buffered in $this->content already" branch), and the underlying
     * transport legitimately replies with one more, harmless confirmation chunk for an
     * already-finished response. Rejecting $r->yieldedState whenever it isn't *exactly*
     * FIRST_CHUNK_YIELDED would also reject that legitimate confirmation chunk for a response
     * that's already fully, successfully done -- exactly the scenario RetryableHttpClient hits
     * when getContent() is called twice on a retried response.
     */
    public function testGettingContentTwiceOnAnAlreadyCompletedResponseDoesNotThrow()
    {
        $passthru = static function ($chunk, $context) {
            yield $chunk;
        };

        $response = new AsyncResponse(new MockHttpClient([new MockResponse('body')]), 'GET', 'http://example.com/', [], $passthru);

        $this->assertSame('body', $response->getContent());
        $this->assertSame('body', $response->getContent());
    }

    public function testPassthruCannotSwallowTheLastChunk()
    {
        // A plain (non-generator) callback: returning null for a chunk means "swallow it".
        // Swallowing intermediate chunks is fine, but the last one must not be swallowed.
        $passthru = static fn ($chunk, $context) => null;

        $response = new AsyncResponse(new MockHttpClient([new MockResponse('body')]), 'GET', 'http://example.com/', [], $passthru);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('A chunk passthru cannot swallow the last chunk.');

        $response->getContent();
    }

    public function testPassthruMustReturnAnIterator()
    {
        $passthru = static fn ($chunk, $context) => 'not-an-iterator';

        $response = new AsyncResponse(new MockHttpClient([new MockResponse('body')]), 'GET', 'http://example.com/', [], $passthru);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('A chunk passthru must return an "Iterator", "string" returned.');

        $response->getContent();
    }

    public function testPassthruCannotYieldMoreThanOneFirstChunk()
    {
        $passthru = static function ($chunk, $context) {
            if ($chunk->isFirst()) {
                yield $chunk;
                yield $chunk; // the same "isFirst" chunk again -> a second openBuffer() call

                return;
            }

            yield $chunk;
        };

        $response = new AsyncResponse(new MockHttpClient([new MockResponse('body')]), 'GET', 'http://example.com/', [], $passthru);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('A chunk passthru cannot yield more than one "isFirst()" chunk.');

        $response->getContent();
    }

    public function testPassthruCannotYieldAfterAnIsLastChunk()
    {
        // Only misbehave once: __destruct() feeds a synthetic isLast chunk through the same
        // passthru while cleaning up after the exception below, and it must not trip this a
        // second time -- that would throw again from within __destruct() itself.
        $triggered = false;
        $passthru = static function ($chunk, $context) use (&$triggered) {
            yield $chunk;

            if ($chunk->isLast() && !$triggered) {
                $triggered = true;

                yield $chunk;
            }
        };

        $response = new AsyncResponse(new MockHttpClient([new MockResponse('body')]), 'GET', 'http://example.com/', [], $passthru);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('A chunk passthru cannot yield after an "isLast()" chunk.');

        $response->getContent();
    }
}
