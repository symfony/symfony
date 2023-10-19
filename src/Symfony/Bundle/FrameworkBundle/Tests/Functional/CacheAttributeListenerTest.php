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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CacheAttributeListenerTest extends AbstractWebTestCase
{
    public function testAnonimousUserWithEtag()
    {
        $client = self::createClient(['test_case' => 'CacheAttributeListener']);

        $client->request('GET', '/', server: ['HTTP_IF_NONE_MATCH' => sprintf('"%s"', hash('sha256', '12345'))]);

        self::assertTrue($client->getResponse()->isRedirect('http://localhost/login'));
    }

    public function testAnonimousUserWithoutEtag()
    {
        $client = self::createClient(['test_case' => 'CacheAttributeListener']);

        $client->request('GET', '/');

        self::assertTrue($client->getResponse()->isRedirect('http://localhost/login'));
    }

    public function testLoggedInUserWithEtag()
    {
        $client = self::createClient(['test_case' => 'CacheAttributeListener']);

        $client->loginUser(new InMemoryUser('the-username', 'the-password', ['ROLE_USER']));
        $client->request('GET', '/', server: ['HTTP_IF_NONE_MATCH' => sprintf('"%s"', hash('sha256', '12345'))]);

        $response = $client->getResponse();

        self::assertSame(304, $response->getStatusCode());
        self::assertSame('', $response->getContent());
    }

    public function testLoggedInUserWithoutEtag()
    {
        $client = self::createClient(['test_case' => 'CacheAttributeListener']);

        $client->loginUser(new InMemoryUser('the-username', 'the-password', ['ROLE_USER']));
        $client->request('GET', '/');

        $response = $client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Hi there!', $response->getContent());
    }
}

class TestEntityValueResolver implements ValueResolverInterface
{
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        return Post::class === $argument->getType() ? [new Post()] : [];
    }
}

class Post
{
    public function getId(): int
    {
        return 1;
    }

    public function getEtag(): string
    {
        return '12345';
    }
}

class WithAttributesController
{
    #[IsGranted('ROLE_USER')]
    #[Cache(etag: 'post.getEtag()')]
    public function __invoke(Post $post): Response
    {
        return new Response('Hi there!');
    }
}
