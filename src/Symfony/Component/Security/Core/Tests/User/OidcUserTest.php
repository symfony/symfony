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

    public function testCreateFullUserWithAdditionalClaimsUsingPositionalParameters()
    {
        $this->assertEquals(new OidcUser(
            userIdentifier: 'john.doe',
            roles: ['ROLE_USER', 'ROLE_ADMIN'],
            sub: 'e21bf182-1538-406e-8ccb-e25a17aba39f',
            name: 'John DOE',
            givenName: 'John',
            familyName: 'DOE',
            middleName: 'Fitzgerald',
            nickname: 'Johnny',
            preferredUsername: 'john.doe',
            profile: 'https://www.example.com/john-doe',
            picture: 'https://www.example.com/pics/john-doe.jpg',
            website: 'https://www.example.com',
            email: 'john.doe@example.com',
            emailVerified: true,
            gender: 'male',
            birthdate: '1980-05-15',
            zoneinfo: 'Europe/Paris',
            locale: 'fr-FR',
            phoneNumber: '+33 (0) 6 12 34 56 78',
            phoneNumberVerified: false,
            address: [
                'formatted' => '1 Rue des Moulins 75000 Paris - France',
                'street_address' => '1 Rue des Moulins',
                'locality' => 'Paris',
                'region' => 'Île-de-France',
                'postal_code' => '75000',
                'country' => 'France',
            ],
            updatedAt: (new \DateTimeImmutable())->setTimestamp(1669628917),
            additionalClaims: [
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
            userIdentifier: 'john.doe',
            roles: ['ROLE_USER', 'ROLE_ADMIN'],
            sub: 'e21bf182-1538-406e-8ccb-e25a17aba39f',
            name: 'John DOE',
            givenName: 'John',
            familyName: 'DOE',
            middleName: 'Fitzgerald',
            nickname: 'Johnny',
            preferredUsername: 'john.doe',
            profile: 'https://www.example.com/john-doe',
            picture: 'https://www.example.com/pics/john-doe.jpg',
            website: 'https://www.example.com',
            email: 'john.doe@example.com',
            emailVerified: true,
            gender: 'male',
            birthdate: '1980-05-15',
            zoneinfo: 'Europe/Paris',
            locale: 'fr-FR',
            phoneNumber: '+33 (0) 6 12 34 56 78',
            phoneNumberVerified: false,
            address: [
                'formatted' => '1 Rue des Moulins 75000 Paris - France',
                'street_address' => '1 Rue des Moulins',
                'locality' => 'Paris',
                'region' => 'Île-de-France',
                'postal_code' => '75000',
                'country' => 'France',
            ],
            updatedAt: (new \DateTimeImmutable())->setTimestamp(1669628917),
            additionalClaims: [
                [
                    'username' => 'jane.doe@example.com',
                ],
                12345,
            ],
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
