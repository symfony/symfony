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
use Symfony\Component\Security\Core\Attribute\AsVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\User\InMemoryUser;

class CallableVoterTest extends AbstractWebTestCase
{
    #[TestWith([false])]
    #[TestWith([true])]
    public function testMethodsMarkedWithAsVoterVote(bool $debug)
    {
        $kernel = self::createKernel(['test_case' => 'CallableVoter', 'debug' => $debug]);
        $kernel->boot();
        $container = $kernel->getContainer();

        $user = new InMemoryUser('alice', 'test', ['ROLE_USER']);
        $container->get('functional_test.security.token_storage')->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        $security = $container->get('functional_test.security.helper');

        CallableVoterTestPostRules::$instances = 0;

        $this->assertTrue($security->isGranted('ROLE_USER'));
        $this->assertSame(0, CallableVoterTestPostRules::$instances, 'The service declaring the rules is built only when a voter calls them.');

        $this->assertTrue($security->isGranted('POST_EDIT', new CallableVoterTestPost('alice')));
        $this->assertFalse($security->isGranted('POST_EDIT', new CallableVoterTestPost('bob')), 'Under the "unanimous" strategy, all voters abstaining denies the access.');

        $accessDecision = new AccessDecision();
        $this->assertFalse($security->isGranted('POST_EDIT', new CallableVoterTestPost('alice', published: true), $accessDecision));
        $this->assertSame('Access Denied. A published post cannot be edited.', $accessDecision->getMessage());

        $this->assertTrue($security->isGranted('POST_VIEW', new CallableVoterTestPost('bob', published: true)), 'A static method votes too.');
        $this->assertSame(1, CallableVoterTestPostRules::$instances);
    }
}

class CallableVoterTestPost
{
    public function __construct(
        public string $author,
        public bool $published = false,
    ) {
    }
}

class CallableVoterTestPostRules
{
    public static int $instances = 0;

    public function __construct()
    {
        ++self::$instances;
    }

    #[AsVoter(['POST_VIEW', 'POST_EDIT'])]
    public function isAuthor(CallableVoterTestPost $post, TokenInterface $token): ?true
    {
        return $post->author === $token->getUserIdentifier() ?: null;
    }

    #[AsVoter('POST_EDIT')]
    public function isPublished(CallableVoterTestPost $post, ?Vote $vote): ?false
    {
        if (!$post->published) {
            return null;
        }

        $vote?->addReason('A published post cannot be edited.');

        return false;
    }

    #[AsVoter('POST_VIEW')]
    public static function isPublic(CallableVoterTestPost $post): ?true
    {
        return $post->published ?: null;
    }
}
