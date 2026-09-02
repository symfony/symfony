<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\User;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\OidcUser;

class OidcUserTest extends TestCase
{
    public function testCannotCreateUserWithoutSubProperty()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "sub" claim cannot be empty.');

        new OidcUser();
    }

    public function testFromClaims()
    {
        $user = OidcUser::fromClaims([
            'sub' => 'e21bf182-1538-406e-8ccb-e25a17aba39f',
            'preferred_username' => 'john.doe',
            'email' => 'john.doe@example.com',
            'email_verified' => 'true',
            'updated_at' => 1669628917,
            'custom_id' => 12345,
            'nickname' => '',
        ]);

        $this->assertSame('e21bf182-1538-406e-8ccb-e25a17aba39f', $user->getUserIdentifier());
        $this->assertSame('john.doe', $user->getPreferredUsername());
        $this->assertSame('john.doe@example.com', $user->getEmail());
        $this->assertTrue($user->getEmailVerified());
        $this->assertEquals((new \DateTimeImmutable())->setTimestamp(1669628917), $user->getUpdatedAt());
        $this->assertNull($user->getNickname());
        $this->assertSame(['customId' => 12345], $user->getAdditionalClaims());
        $this->assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testFromClaimsGrantsTheRolesItIsGiven()
    {
        // this is how a user provider maps the claims of its choice onto roles
        $user = OidcUser::fromClaims(['sub' => 'e21bf182-1538-406e-8ccb-e25a17aba39f', 'roles' => ['ROLE_ADMIN']]);

        $this->assertSame(['ROLE_ADMIN'], $user->getRoles());
    }

    public function testFromClaimsIgnoresMistypedClaims()
    {
        $user = OidcUser::fromClaims([
            'sub' => 'valid-sub-123',
            'name' => ['formatted' => 'John Doe'],
            'updated_at' => '2024-01-01T00:00:00Z',
            'address' => 'not-a-struct',
            'email_verified' => ['yes'],
            'roles' => 'ROLE_ADMIN',
        ]);

        $this->assertSame('valid-sub-123', $user->getSub());
        $this->assertNull($user->getName());
        $this->assertNull($user->getUpdatedAt());
        $this->assertNull($user->getAddress());
        $this->assertNull($user->getEmailVerified());
        $this->assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testFromClaimsAcceptsNumericStringUpdatedAt()
    {
        $user = OidcUser::fromClaims([
            'sub' => 'valid-sub-123',
            'updated_at' => '1700000000',
            'custom' => ['a' => 1],
        ]);

        $this->assertSame(1700000000, $user->getUpdatedAt()?->getTimestamp());
        $this->assertSame(['custom' => ['a' => 1]], $user->getAdditionalClaims());
    }

    public function testFromClaimsKeepsTheCalledClass()
    {
        $user = TestOidcUser::fromClaims(['sub' => 'e21bf182-1538-406e-8ccb-e25a17aba39f']);

        $this->assertInstanceOf(TestOidcUser::class, $user);
    }

    public function testCreateFullUserWithAdditionalClaimsUsingPositionalParameters()
    {
        $this->assertEquals(new OidcUser(
            'john.doe',
            ['ROLE_USER', 'ROLE_ADMIN'],
            'e21bf182-1538-406e-8ccb-e25a17aba39f',
            'John DOE',
            'John',
            'DOE',
            'Fitzgerald',
            'Johnny',
            'john.doe',
            'https://www.example.com/john-doe',
            'https://www.example.com/pics/john-doe.jpg',
            'https://www.example.com',
            'john.doe@example.com',
            true,
            'male',
            '1980-05-15',
            'Europe/Paris',
            'fr-FR',
            '+33 (0) 6 12 34 56 78',
            false,
            [
                'formatted' => '1 Rue des Moulins 75000 Paris - France',
                'street_address' => '1 Rue des Moulins',
                'locality' => 'Paris',
                'region' => 'Île-de-France',
                'postal_code' => '75000',
                'country' => 'France',
            ],
            (new \DateTimeImmutable())->setTimestamp(1669628917),
            ...[
                'impersonator' => [
                    'username' => 'jane.doe@example.com',
                ],
                'customId' => 12345,
            ],
        ), new OidcUser(...[
            'userIdentifier' => 'john.doe',
            'roles' => ['ROLE_USER', 'ROLE_ADMIN'],
            'sub' => 'e21bf182-1538-406e-8ccb-e25a17aba39f',
            'name' => 'John DOE',
            'givenName' => 'John',
            'familyName' => 'DOE',
            'middleName' => 'Fitzgerald',
            'nickname' => 'Johnny',
            'preferredUsername' => 'john.doe',
            'profile' => 'https://www.example.com/john-doe',
            'picture' => 'https://www.example.com/pics/john-doe.jpg',
            'website' => 'https://www.example.com',
            'email' => 'john.doe@example.com',
            'emailVerified' => true,
            'gender' => 'male',
            'birthdate' => '1980-05-15',
            'zoneinfo' => 'Europe/Paris',
            'locale' => 'fr-FR',
            'phoneNumber' => '+33 (0) 6 12 34 56 78',
            'phoneNumberVerified' => false,
            'address' => [
                'formatted' => '1 Rue des Moulins 75000 Paris - France',
                'street_address' => '1 Rue des Moulins',
                'locality' => 'Paris',
                'region' => 'Île-de-France',
                'postal_code' => '75000',
                'country' => 'France',
            ],
            'updatedAt' => (new \DateTimeImmutable())->setTimestamp(1669628917),
            'impersonator' => [
                'username' => 'jane.doe@example.com',
            ],
            'customId' => 12345,
        ]));
    }

    public function testCreateFullUserWithAdditionalClaims()
    {
        $this->assertEquals(new OidcUser(
            'john.doe',
            ['ROLE_USER', 'ROLE_ADMIN'],
            'e21bf182-1538-406e-8ccb-e25a17aba39f',
            'John DOE',
            'John',
            'DOE',
            'Fitzgerald',
            'Johnny',
            'john.doe',
            'https://www.example.com/john-doe',
            'https://www.example.com/pics/john-doe.jpg',
            'https://www.example.com',
            'john.doe@example.com',
            true,
            'male',
            '1980-05-15',
            'Europe/Paris',
            'fr-FR',
            '+33 (0) 6 12 34 56 78',
            false,
            [
                'formatted' => '1 Rue des Moulins 75000 Paris - France',
                'street_address' => '1 Rue des Moulins',
                'locality' => 'Paris',
                'region' => 'Île-de-France',
                'postal_code' => '75000',
                'country' => 'France',
            ],
            (new \DateTimeImmutable())->setTimestamp(1669628917),
            [
                'username' => 'jane.doe@example.com',
            ],
            12345,
        ), new OidcUser(
            'john.doe',
            ['ROLE_USER', 'ROLE_ADMIN'],
            'e21bf182-1538-406e-8ccb-e25a17aba39f',
            'John DOE',
            'John',
            'DOE',
            'Fitzgerald',
            'Johnny',
            'john.doe',
            'https://www.example.com/john-doe',
            'https://www.example.com/pics/john-doe.jpg',
            'https://www.example.com',
            'john.doe@example.com',
            true,
            'male',
            '1980-05-15',
            'Europe/Paris',
            'fr-FR',
            '+33 (0) 6 12 34 56 78',
            false,
            [
                'formatted' => '1 Rue des Moulins 75000 Paris - France',
                'street_address' => '1 Rue des Moulins',
                'locality' => 'Paris',
                'region' => 'Île-de-France',
                'postal_code' => '75000',
                'country' => 'France',
            ],
            (new \DateTimeImmutable())->setTimestamp(1669628917),
            [
                'username' => 'jane.doe@example.com',
            ],
            12345
        ));
    }
}

class TestOidcUser extends OidcUser
{
}
