<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestPayloadValueResolver;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class IsGrantedTest extends AbstractWebTestCase
{
    #[TestWith(['config.yml'])]
    #[TestWith(['config_decorated.yml'])]
    public function testVoterReceivesThePayloadMappedFromTheRequest(string $rootConfig)
    {
        $client = $this->createClient(['test_case' => 'IsGranted', 'root_config' => $rootConfig]);

        $client->request('POST', '/post', [], [], ['CONTENT_TYPE' => 'application/json'], '{"title": "allowed"}');
        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertSame('allowed', $client->getResponse()->getContent());

        $client->request('POST', '/post', [], [], ['CONTENT_TYPE' => 'application/json'], '{"title": "denied"}');
        $this->assertSame(403, $client->getResponse()->getStatusCode());
    }

    public function testChecksWorkWithoutTheSerializer()
    {
        $client = $this->createClient(['test_case' => 'IsGranted', 'root_config' => 'config_no_serializer.yml']);

        $client->request('GET', '/posts');
        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertSame('posts', $client->getResponse()->getContent());
    }

    public function testChecksRunInTheOrderTheyAreDeclared()
    {
        $client = $this->createClient(['test_case' => 'IsGranted', 'root_config' => 'config.yml']);

        $client->request('POST', '/post/closed', [], [], ['CONTENT_TYPE' => 'application/json'], '{"title": ');
        $this->assertSame(403, $client->getResponse()->getStatusCode());

        $client->request('POST', '/post/closed-after-mapping', [], [], ['CONTENT_TYPE' => 'application/json'], '{"title": ');
        $this->assertSame(400, $client->getResponse()->getStatusCode());
    }
}

class IsGrantedPost
{
    public function __construct(
        public string $title,
    ) {
    }
}

class IsGrantedPostController
{
    #[IsGranted('POST_CREATE', 'post', statusCode: 403)]
    public function create(#[MapRequestPayload] IsGrantedPost $post): Response
    {
        return new Response($post->title);
    }

    #[IsGranted('POST_CLOSED', statusCode: 403)]
    #[IsGranted('POST_CREATE', 'post', statusCode: 403)]
    public function closed(#[MapRequestPayload] IsGrantedPost $post): Response
    {
        return new Response($post->title);
    }

    #[IsGranted('PUBLIC_ACCESS')]
    public function list(): Response
    {
        return new Response('posts');
    }

    #[IsGranted('POST_CREATE', 'post', statusCode: 403)]
    #[IsGranted('POST_CLOSED', statusCode: 403)]
    public function closedAfterMapping(#[MapRequestPayload] IsGrantedPost $post): Response
    {
        return new Response($post->title);
    }
}

class IsGrantedPostVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return 'POST_CREATE' === $attribute && $subject instanceof IsGrantedPost;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return 'allowed' === $subject->title;
    }
}

class IsGrantedDecoratingValueResolver implements ValueResolverInterface, EventSubscriberInterface
{
    public function __construct(
        private RequestPayloadValueResolver $inner,
    ) {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        return $this->inner->resolve($request, $argument);
    }

    public function onKernelControllerArguments(ControllerArgumentsEvent $event): void
    {
        $this->inner->onKernelControllerArguments($event);
    }

    public static function getSubscribedEvents(): array
    {
        return RequestPayloadValueResolver::getSubscribedEvents();
    }
}
