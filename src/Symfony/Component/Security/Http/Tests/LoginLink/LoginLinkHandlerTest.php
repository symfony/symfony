<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\LoginLink;

use ParagonIE\Halite\KeyFactory;
use ParagonIE\Halite\Symmetric\Crypto;
use ParagonIE\HiddenString\HiddenString;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\LoginLink\Exception\ExpiredLoginLinkException;
use Symfony\Component\Security\Http\LoginLink\Exception\InvalidLoginLinkException;
use Symfony\Component\Security\Http\LoginLink\ExpiredLoginLinkStorage;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandler;

class LoginLinkHandlerTest extends TestCase
{
    /** @var MockObject|UrlGeneratorInterface */
    private $router;
    /** @var MockObject|UserProviderInterface */
    private $userProvider;
    /** @var PropertyAccessorInterface */
    private $propertyAccessor;
    /** @var MockObject|ExpiredLoginLinkStorage */
    private $expiredLinkStorage;

    protected function setUp(): void
    {
        $this->router = $this->createMock(UrlGeneratorInterface::class);
        $this->userProvider = $this->createMock(UserProviderInterface::class);
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        $this->expiredLinkStorage = $this->createMock(ExpiredLoginLinkStorage::class);
    }

    private function encrypt(string $message): string
    {
        $key = KeyFactory::deriveEncryptionKey(
            new HiddenString('s3cret'),
            "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f",
            KeyFactory::MODERATE
        );
        $cipherText = Crypto::encrypt(new HiddenString($message), $key);

        return $cipherText;
    }

    /**
     * @dataProvider provideCreateLoginLinkData
     * @group time-sensitive
     */
    public function testCreateLoginLink($user, array $extraProperties)
    {
        $this->router->expects($this->once())
            ->method('generate')
            ->with(
                'app_check_login_link_route',
                $this->callback(function ($parameters) use ($extraProperties) {
                    return isset($parameters['user'])
                        && isset($parameters['expires'])
                        && isset($parameters['hash'])
                        // make sure hash is what we expect
                        && $parameters['hash'] === $this->createSignatureHash('weaverryan', time() + 600, array_values($extraProperties));
                }),
                UrlGeneratorInterface::ABSOLUTE_URL
            )
            ->willReturn('https://example.com/login/verify?user=weaverryan&hash=abchash&expires=1601235000');

        $loginLink = $this->createLinker([], array_keys($extraProperties))->createLoginLink($user);
        $this->assertSame('https://example.com/login/verify?user=weaverryan&hash=abchash&expires=1601235000', $loginLink->getUrl());
    }

    public function provideCreateLoginLinkData()
    {
        yield [
            new TestLoginLinkHandlerUser('weaverryan', 'ryan@symfonycasts.com', 'pwhash'),
            ['emailProperty' => 'ryan@symfonycasts.com', 'passwordProperty' => 'pwhash'],
        ];

        yield [
            new TestLoginLinkHandlerUser('weaverryan', 'ryan@symfonycasts.com', 'pwhash'),
            ['lastAuthenticatedAt' => ''],
        ];

        yield [
            new TestLoginLinkHandlerUser('weaverryan', 'ryan@symfonycasts.com', 'pwhash', new \DateTime('2020-06-01 00:00:00', new \DateTimeZone('+0000'))),
            ['lastAuthenticatedAt' => '2020-06-01T00:00:00+00:00'],
        ];
    }

    public function testConsumeLoginLink()
    {
        $expires = time() + 500;
        $signature = $this->createSignatureHash('weaverryan', $expires, ['ryan@symfonycasts.com', 'pwhash']);
        $request = Request::create(sprintf('/login/verify?user=%s&hash=%s&expires=%d', $this->encrypt('weaverryan'), $signature, $expires));

        $user = new TestLoginLinkHandlerUser('weaverryan', 'ryan@symfonycasts.com', 'pwhash');
        $this->userProvider->expects($this->once())
            ->method('loadUserByUsername')
            ->with('weaverryan')
            ->willReturn($user);

        $this->expiredLinkStorage->expects($this->once())
            ->method('incrementUsages')
            ->with($signature);

        $linker = $this->createLinker(['max_uses' => 3]);
        $actualUser = $linker->consumeLoginLink($request);
        $this->assertSame($user, $actualUser);
    }

    /**
     * @group legacy
     */
    public function testConsumeLoginLinkUnencryptedUser()
    {
        $expires = time() + 500;
        $signature = $this->createSignatureHash('weaverryan', $expires, ['ryan@symfonycasts.com', 'pwhash']);
        $request = Request::create(sprintf('/login/verify?user=weaverryan&hash=%s&expires=%d', $signature, $expires));

        $user = new TestLoginLinkHandlerUser('weaverryan', 'ryan@symfonycasts.com', 'pwhash');
        $this->userProvider->expects($this->once())
            ->method('loadUserByUsername')
            ->with('weaverryan')
            ->willReturn($user);

        $this->expiredLinkStorage->expects($this->once())
            ->method('incrementUsages')
            ->with($signature);

        $linker = $this->createLinker(['max_uses' => 3]);
        $actualUser = $linker->consumeLoginLink($request);
        $this->assertSame($user, $actualUser);
    }

    public function testConsumeLoginLinkWithExpired()
    {
        $this->expectException(ExpiredLoginLinkException::class);
        $expires = time() - 500;
        $signature = $this->createSignatureHash('weaverryan', $expires, ['ryan@symfonycasts.com', 'pwhash']);
        $request = Request::create(sprintf('/login/verify?user=%s&hash=%s&expires=%d', $this->encrypt('weaverryan'), $signature, $expires));

        $user = new TestLoginLinkHandlerUser('weaverryan', 'ryan@symfonycasts.com', 'pwhash');
        $this->userProvider->expects($this->once())
            ->method('loadUserByUsername')
            ->with('weaverryan')
            ->willReturn($user);

        $linker = $this->createLinker(['max_uses' => 3]);
        $linker->consumeLoginLink($request);
    }

    public function testConsumeLoginLinkWithUserNotFound()
    {
        $this->expectException(InvalidLoginLinkException::class);
        $request = Request::create(sprintf('/login/verify?user=%s&hash=thehash&expires=10000', $this->encrypt('weaverryan')));

        $this->userProvider->expects($this->once())
            ->method('loadUserByUsername')
            ->with('weaverryan')
            ->willThrowException(new UsernameNotFoundException());

        $linker = $this->createLinker();
        $linker->consumeLoginLink($request);
    }

    public function testConsumeLoginLinkWithDifferentSignature()
    {
        $this->expectException(InvalidLoginLinkException::class);
        $request = Request::create(sprintf('/login/verify?user=%s&hash=fake_hash&expires=%d', $this->encrypt('weaverryan'), time() + 500));

        $user = new TestLoginLinkHandlerUser('weaverryan', 'ryan@symfonycasts.com', 'pwhash');
        $this->userProvider->expects($this->once())
            ->method('loadUserByUsername')
            ->with('weaverryan')
            ->willReturn($user);

        $linker = $this->createLinker();
        $linker->consumeLoginLink($request);
    }

    public function testConsumeLoginLinkExceedsMaxUsage()
    {
        $this->expectException(ExpiredLoginLinkException::class);
        $expires = time() + 500;
        $signature = $this->createSignatureHash('weaverryan', $expires, ['ryan@symfonycasts.com', 'pwhash']);
        $request = Request::create(sprintf('/login/verify?user=%s&hash=%s&expires=%d', $this->encrypt('weaverryan'), $signature, $expires));

        $user = new TestLoginLinkHandlerUser('weaverryan', 'ryan@symfonycasts.com', 'pwhash');
        $this->userProvider->expects($this->once())
            ->method('loadUserByUsername')
            ->with('weaverryan')
            ->willReturn($user);

        $this->expiredLinkStorage->expects($this->once())
            ->method('countUsages')
            ->with($signature)
            ->willReturn(3);

        $linker = $this->createLinker(['max_uses' => 3]);
        $linker->consumeLoginLink($request);
    }

    private function createSignatureHash(string $username, int $expires, array $extraFields): string
    {
        $fields = [base64_encode($username), $expires];
        foreach ($extraFields as $extraField) {
            $fields[] = base64_encode($extraField);
        }

        // matches hash logic in the class
        return base64_encode(hash_hmac('sha256', implode(':', $fields), 's3cret'));
    }

    private function createLinker(array $options = [], array $extraProperties = ['emailProperty', 'passwordProperty']): LoginLinkHandler
    {
        $options = array_merge([
            'lifetime' => 600,
            'route_name' => 'app_check_login_link_route',
        ], $options);

        return new LoginLinkHandler($this->router, $this->userProvider, $this->propertyAccessor, $extraProperties, 's3cret', $options, $this->expiredLinkStorage);
    }
}

class TestLoginLinkHandlerUser implements UserInterface
{
    public $username;
    public $emailProperty;
    public $passwordProperty;
    public $lastAuthenticatedAt;

    public function __construct($username, $emailProperty, $passwordProperty, $lastAuthenticatedAt = null)
    {
        $this->username = $username;
        $this->emailProperty = $emailProperty;
        $this->passwordProperty = $passwordProperty;
        $this->lastAuthenticatedAt = $lastAuthenticatedAt;
    }

    public function getRoles()
    {
        return [];
    }

    public function getPassword()
    {
        return $this->passwordProperty;
    }

    public function getSalt()
    {
        return '';
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function eraseCredentials()
    {
    }
}
