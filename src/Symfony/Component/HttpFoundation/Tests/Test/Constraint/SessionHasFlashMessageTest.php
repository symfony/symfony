<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\Test\Constraint;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpFoundation\Test\Constraint\SessionHasFlashMessage;

class SessionHasFlashMessageTest extends TestCase
{
    #[DataProvider('provideMessages')]
    public function testSuccessfulCase(string|array $flashMessages)
    {
        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $session->getFlashBag()->set('foo', 'bar');

        $constraint = new SessionHasFlashMessage('foo', $flashMessages);
        $this->assertTrue($constraint->evaluate($request, '', true));
    }

    #[DataProvider('provideMessages')]
    public function testNoMessage(string|array $flashMessages)
    {
        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $constraint = new SessionHasFlashMessage('foo', $flashMessages);

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage(\sprintf(
            'Failed asserting that session has flash message of type "foo" containing: %s.',
            \is_string($flashMessages) ? $flashMessages : implode(', ', $flashMessages),
        ));

        $constraint->evaluate($request);
    }

    #[DataProvider('provideMessages')]
    public function testNoSession(string|array $flashMessages)
    {
        $request = new Request();

        $constraint = new SessionHasFlashMessage('foo', $flashMessages);

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage(\sprintf(
            'Failed asserting that session has flash message of type "foo" containing: %s, because the Request does not have a Session.',
            \is_string($flashMessages) ? $flashMessages : implode(', ', $flashMessages),
        ));

        $constraint->evaluate($request);
    }

    #[DataProvider('provideMessages')]
    public function testNoFlashBag(string|array $flashMessages)
    {
        $request = new Request();
        $session = $this->createStub(SessionInterface::class);
        $request->setSession($session);

        $constraint = new SessionHasFlashMessage('foo', $flashMessages);

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage(\sprintf(
            'Failed asserting that session has flash message of type "foo" containing: %s, because the Session does not have a FlashBag',
            \is_string($flashMessages) ? $flashMessages : implode(', ', $flashMessages),
        ));

        $constraint->evaluate($request);
    }

    public static function provideMessages(): iterable
    {
        yield ['bar'];
        yield [['bar', 'baz']];
    }
}
