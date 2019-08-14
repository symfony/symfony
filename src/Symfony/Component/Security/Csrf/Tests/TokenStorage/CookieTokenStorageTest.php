<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Csrf\Tests\TokenStorage;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use Symfony\Component\Security\Csrf\TokenStorage\CookieTokenStorage;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class CookieTokenStorageTest extends TestCase
{
    const COOKIE_NAMESPACE = 'foobar';

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var CookieTokenStorage
     */
    private $storage;

    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
        $this->requestStack->push(new Request());

        $this->storage = new CookieTokenStorage($this->requestStack, 's3cr3t', self::COOKIE_NAMESPACE);
    }

    public function testStoreTokenAddsCookies()
    {
        $this->storage->setToken('token_id', 'TOKEN');
        $this->storage->sendCookies($response = new Response(), $this->requestStack->getMasterRequest());

        $cookies = $response->headers->getCookies();
        $this->assertCount(1, $cookies);
        $this->assertLessThan(time() + 3601, $cookies[0]->getExpiresTime());
    }

    public function testCheckTokenInTransientStorage()
    {
        $this->storage->setToken('token_id', 'TOKEN');

        $this->assertTrue($this->storage->hasToken('token_id'));
    }

    public function testGetExistingToken()
    {
        $response = $this->generateCookieResponse('token_id', 'TOKEN');
        $cookies = $response->headers->getCookies();
        $this->requestStack->getMasterRequest()->cookies->set($cookies[0]->getName(), $cookies[0]->getValue());

        $this->assertSame('TOKEN', $this->storage->getToken('token_id'));
    }

    public function testGetNonExistingToken()
    {
        $this->expectException(TokenNotFoundException::class);
        $this->storage->getToken('token_id');
    }

    public function testInvalidToken()
    {
        $this->expectException(TokenNotFoundException::class);

        $response = $this->generateCookieResponse('token_id', 'TOKEN');
        $cookies = $response->headers->getCookies();
        $this->requestStack->getMasterRequest()->cookies->set($cookies[0]->getName(), $cookies[0]->getValue());

        $response = $this->generateCookieResponse('token_id', 'TOKEN');
        $cookies = $response->headers->getCookies();
        $this->requestStack->getMasterRequest()->cookies->set($cookies[0]->getName(), $cookies[0]->getValue().'--');

        $this->storage->getToken('token_id');
    }

    public function testExpiredToken()
    {
        $this->expectException(TokenNotFoundException::class);

        $previousRequestStack = new RequestStack();
        $previousRequestStack->push($previousRequest = new Request());
        $previousStorage = new CookieTokenStorage($previousRequestStack, 's3cr3t', self::COOKIE_NAMESPACE, -1);
        $previousStorage->setToken('token_id', 'TOKEN');
        $previousStorage->sendCookies($response = new Response(), $previousRequest);

        $cookies = $response->headers->getCookies();
        $this->requestStack->getMasterRequest()->cookies->set($cookies[0]->getName(), $cookies[0]->getValue());

        $this->storage->getToken('token_id');
    }

    public function testRemoveNonExistingToken()
    {
        $this->assertNull($this->storage->removeToken('token_id'));
    }

    public function testRemoveExistingToken()
    {
        $previousResponse = $this->generateCookieResponse('token_id', 'TOKEN');
        $cookies = $previousResponse->headers->getCookies();
        $this->requestStack->getMasterRequest()->cookies->set($cookies[0]->getName(), $cookies[0]->getValue());

        $deletedToken = $this->storage->removeToken('token_id');
        $this->storage->sendCookies($response = new Response(), $this->requestStack->getMasterRequest());

        $cookies = $response->headers->getCookies();
        $this->assertCount(1, $cookies);
        $this->assertNull($cookies[0]->getValue());
        $this->assertSame('TOKEN', $deletedToken);
    }

    public function testClearRemovesAllTokensFromTheConfiguredNamespace()
    {
        $response = $this->generateCookieResponse('token_id', 'TOKEN');
        $cookies = $response->headers->getCookies();
        $this->requestStack->getMasterRequest()->cookies->set($cookies[0]->getName(), $cookies[0]->getValue());

        $this->storage->clear();
        $this->storage->sendCookies($response = new Response(), $this->requestStack->getMasterRequest());

        $cookies = $response->headers->getCookies();
        $this->assertCount(1, $cookies);
        $this->assertNull($cookies[0]->getValue());
    }

    private function generateCookieResponse(string $tokenId, string $token): Response
    {
        $previousRequestStack = new RequestStack();
        $previousRequestStack->push($previousRequest = new Request());
        $previousStorage = new CookieTokenStorage($previousRequestStack, 's3cr3t', self::COOKIE_NAMESPACE);
        $previousStorage->setToken($tokenId, $token);
        $previousStorage->sendCookies($response = new Response(), $previousRequest);

        return $response;
    }
}
