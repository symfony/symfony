<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

class SecurityTest extends AbstractWebTestCase
{
    #[DataProvider('getUsers')]
    public function testLoginUser(string $username, array $roles, ?string $firewallContext)
    {
        $user = new InMemoryUser($username, 'the-password', $roles);
        $client = $this->createClient(['test_case' => 'Security', 'root_config' => 'config.yml']);

        if (null === $firewallContext) {
            $client->loginUser($user);
        } else {
            $client->loginUser($user, $firewallContext);
        }

        $client->request('GET', '/'.($firewallContext ?? 'main').'/user_profile');
        $this->assertEquals('Welcome '.$username.'!', $client->getResponse()->getContent());
    }

    public static function getUsers()
    {
        yield ['the-username', ['ROLE_FOO'], null];
        yield ['the-username', ['ROLE_FOO'], 'main'];
        yield ['other-username', ['ROLE_FOO'], 'custom'];
        yield ['stateless-username', ['ROLE_FOO'], 'stateless'];

        yield ['the-username', ['ROLE_FOO'], null];
        yield ['no-role-username', [], null];
    }

    public function testLoginUserMultipleRequests()
    {
        $user = new InMemoryUser('the-username', 'the-password', ['ROLE_FOO']);
        $client = $this->createClient(['test_case' => 'Security', 'root_config' => 'config.yml']);
        $client->loginUser($user);

        $client->request('GET', '/main/user_profile');
        $this->assertEquals('Welcome the-username!', $client->getResponse()->getContent());

        $client->request('GET', '/main/user_profile');
        $this->assertEquals('Welcome the-username!', $client->getResponse()->getContent());
    }

    public function testLoginInBetweenRequests()
    {
        $user = new InMemoryUser('the-username', 'the-password', ['ROLE_FOO']);
        $client = $this->createClient(['test_case' => 'Security', 'root_config' => 'config.yml']);

        $client->request('GET', '/main/user_profile');
        $this->assertTrue($client->getResponse()->isRedirect('http://localhost/login'));

        $client->loginUser($user);

        $client->request('GET', '/main/user_profile');
        $this->assertEquals('Welcome the-username!', $client->getResponse()->getContent());
    }

    public function testLoginUserMultipleTimes()
    {
        $userFoo = new InMemoryUser('the-username', 'the-password', ['ROLE_FOO']);
        $userBar = new InMemoryUser('no-role-username', 'the-password');
        $client = $this->createClient(['test_case' => 'Security', 'root_config' => 'config.yml']);
        $client->loginUser($userFoo);

        $client->request('GET', '/main/user_profile');
        $this->assertEquals('Welcome the-username!', $client->getResponse()->getContent());

        $client->loginUser($userBar);

        $client->request('GET', '/main/user_profile');
        $this->assertEquals('Welcome no-role-username!', $client->getResponse()->getContent());
    }

    public function testLoginUserThrowsContextualErrorWhenUserGraphIsNotSerializable()
    {
        $user = new SecurityTestUserWithUnserializableField('the-username', ['ROLE_FOO']);
        $client = $this->createClient(['test_case' => 'Security', 'root_config' => 'config.yml']);

        try {
            $client->loginUser($user);
            $this->fail('Expected LogicException was not thrown.');
        } catch (\LogicException $e) {
            $this->assertStringContainsString(SecurityTestUserWithUnserializableField::class, $e->getMessage());
            $this->assertStringContainsString('not serializable', $e->getMessage());
            $this->assertStringContainsString('Implement "__serialize()"/"__unserialize()"', $e->getMessage());
        }
    }

    public function testLoginUserSuggestsReviewWhenUserAlreadyImplementsSerialize()
    {
        $user = new SecurityTestUserWithBrokenSerialize();
        $client = $this->createClient(['test_case' => 'Security', 'root_config' => 'config.yml']);

        try {
            $client->loginUser($user);
            $this->fail('Expected LogicException was not thrown.');
        } catch (\LogicException $e) {
            $this->assertStringContainsString(SecurityTestUserWithBrokenSerialize::class, $e->getMessage());
            $this->assertStringContainsString('Review the "__serialize()" implementation', $e->getMessage());
        }
    }
}

class SecurityTestUserWithUnserializableField implements UserInterface
{
    public \SplFileInfo $file;

    public function __construct(private string $username, private array $roles = [])
    {
        $this->file = new \SplFileInfo(__FILE__);
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }
}

class SecurityTestUserWithBrokenSerialize implements UserInterface
{
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return 'broken-serialize-user';
    }

    public function __serialize(): array
    {
        return ['file' => new \SplFileInfo(__FILE__)];
    }

    public function __unserialize(array $data): void
    {
    }
}
