<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Authenticator\Passport\Badge;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\String\UnicodeString;

use function Symfony\Component\String\u;

class UserBadgeTest extends TestCase
{
    public function testUserNotFound()
    {
        $badge = new UserBadge('dummy', static fn () => null);
        $this->expectException(UserNotFoundException::class);
        $badge->getUser();
    }

    public function testEmptyUserIdentifier()
    {
        $this->expectException(BadCredentialsException::class);
        new UserBadge('', static fn () => null);
    }

    #[DataProvider('provideUserIdentifierNormalizationData')]
    public function testUserIdentifierNormalization(string $identifier, string $expectedNormalizedIdentifier, callable $normalizer)
    {
        $badge = new UserBadge($identifier, static fn () => null, identifierNormalizer: $normalizer);

        static::assertSame($expectedNormalizedIdentifier, $badge->getUserIdentifier());
    }

    public static function provideUserIdentifierNormalizationData(): iterable
    {
        $lowerAndNFKC = static fn (string $identifier) => u($identifier)->normalize(UnicodeString::NFKC)->lower()->toString();
        yield 'Simple lower conversion' => ['SmiTh', 'smith', $lowerAndNFKC];
        yield 'Normalize ﬁ to fi. Other unicode characters are preserved (р, с, ѕ and а)' => ['рrinсeѕѕ.ﬁonа', 'рrinсeѕѕ.fionа', $lowerAndNFKC];
        yield 'Greek characters' => ['ΝιΚόΛΑος', 'νικόλαος', $lowerAndNFKC];

        $slugger = new AsciiSlugger('en');
        $asciiWithPrefix = static fn (string $identifier) => u($slugger->slug($identifier))->ascii()->lower()->prepend('USERID--')->toString();
        yield 'Username with prefix' => ['John Doe 1', 'USERID--john-doe-1', $asciiWithPrefix];

        if (!\extension_loaded('intl')) {
            return;
        }
        $upperAndAscii = static fn (string $identifier) => u($identifier)->ascii()->upper()->toString();
        yield 'Greek to ASCII' => ['ΝιΚόΛΑος', 'NIKOLAOS', $upperAndAscii];
        yield 'Katakana to ASCII' => ['たなかそういち', 'TANAKASOUICHI', $upperAndAscii];
    }

    public function testUserIdentifierNormalizationEnforcesMaxLength()
    {
        $badge = new UserBadge('valid_input', null, null, static fn () => str_repeat('a', UserBadge::MAX_USERNAME_LENGTH + 1));

        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('Username too long.');

        $badge->getUserIdentifier();
    }
}
