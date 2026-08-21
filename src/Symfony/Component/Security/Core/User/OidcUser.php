<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\User;

use function Symfony\Component\String\u;

/**
 * UserInterface implementation used by the security workflows with an OIDC server:
 * the "oidc_login" authenticator and the access-token OIDC handlers.
 */
class OidcUser implements UserInterface
{
    private array $additionalClaims = [];

    public function __construct(
        private ?string $userIdentifier = null,
        private array $roles = ['ROLE_USER'],

        // Standard Claims (https://openid.net/specs/openid-connect-core-1_0.html#StandardClaims)
        private ?string $sub = null,
        private ?string $name = null,
        private ?string $givenName = null,
        private ?string $familyName = null,
        private ?string $middleName = null,
        private ?string $nickname = null,
        private ?string $preferredUsername = null,
        private ?string $profile = null,
        private ?string $picture = null,
        private ?string $website = null,
        private ?string $email = null,
        private ?bool $emailVerified = null,
        private ?string $gender = null,
        private ?string $birthdate = null,
        private ?string $zoneinfo = null,
        private ?string $locale = null,
        private ?string $phoneNumber = null,
        private ?bool $phoneNumberVerified = null,
        private ?array $address = null,
        private ?\DateTimeInterface $updatedAt = null,

        // Additional Claims (https://openid.net/specs/openid-connect-core-1_0.html#AdditionalClaims)
        ...$additionalClaims,
    ) {
        if (null === $sub || '' === $sub) {
            throw new \InvalidArgumentException('The "sub" claim cannot be empty.');
        }

        $this->additionalClaims = $additionalClaims['additionalClaims'] ?? $additionalClaims;
    }

    /**
     * Builds a user from the claims returned by an OIDC provider.
     *
     * Claim names are camel-cased onto the constructor arguments, and the ones matching
     * no argument become additional claims. Every claim is mapped this way, so a "roles"
     * claim does grant the roles it holds: only pass claims you decided to trust, either
     * because you verified where they come from or because you mapped them yourself.
     * Claims whose value does not match the type of their constructor argument are ignored,
     * so that a misbehaving provider cannot break authentication with a TypeError.
     *
     * @param array<string, mixed> $claims
     */
    public static function fromClaims(array $claims): static
    {
        if (!\function_exists('Symfony\Component\String\u')) {
            throw new \LogicException(\sprintf('You cannot use "%s()" since the String component is not installed. Try running "composer require symfony/string".', __METHOD__));
        }

        foreach ($claims as $claim => $value) {
            unset($claims[$claim]);
            if ('' === $value || null === $value) {
                continue;
            }
            $claim = u($claim)->camel()->toString();

            // drop the claims whose value cannot fit their typed constructor argument:
            // a TypeError would escape the authentication flow as a 500 response
            $fits = match ($claim) {
                'roles', 'address', 'additionalClaims' => \is_array($value),
                'emailVerified', 'phoneNumberVerified' => \is_scalar($value),
                'updatedAt' => is_numeric($value),
                'userIdentifier', 'sub', 'name', 'givenName', 'familyName', 'middleName', 'nickname', 'preferredUsername', 'profile', 'picture', 'website', 'email', 'gender', 'birthdate', 'zoneinfo', 'locale', 'phoneNumber' => \is_scalar($value) || $value instanceof \Stringable,
                default => true,
            };

            if ($fits) {
                $claims[$claim] = $value;
            }
        }

        if (isset($claims['updatedAt'])) {
            $claims['updatedAt'] = (new \DateTimeImmutable())->setTimestamp((int) $claims['updatedAt']);
        }

        if (isset($claims['emailVerified'])) {
            $claims['emailVerified'] = (bool) $claims['emailVerified'];
        }

        if (isset($claims['phoneNumberVerified'])) {
            $claims['phoneNumberVerified'] = (bool) $claims['phoneNumberVerified'];
        }

        // a subclass that changes the constructor signature breaks this factory anyway,
        // and late static binding is what lets it keep its own type here
        // @phpstan-ignore new.static
        return new static(...$claims);
    }

    /**
     * OIDC or OAuth specs don't have any "role" notion.
     *
     * If you want to implement "roles" from your OIDC server,
     * send a "roles" constructor argument to this object
     * (e.g.: using a custom UserProvider).
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getUserIdentifier(): string
    {
        return (string) ($this->userIdentifier ?? $this->getSub());
    }

    public function getSub(): ?string
    {
        return $this->sub;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getGivenName(): ?string
    {
        return $this->givenName;
    }

    public function getFamilyName(): ?string
    {
        return $this->familyName;
    }

    public function getMiddleName(): ?string
    {
        return $this->middleName;
    }

    public function getNickname(): ?string
    {
        return $this->nickname;
    }

    public function getPreferredUsername(): ?string
    {
        return $this->preferredUsername;
    }

    public function getProfile(): ?string
    {
        return $this->profile;
    }

    public function getPicture(): ?string
    {
        return $this->picture;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getEmailVerified(): ?bool
    {
        return $this->emailVerified;
    }

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function getBirthdate(): ?string
    {
        return $this->birthdate;
    }

    public function getZoneinfo(): ?string
    {
        return $this->zoneinfo;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function getphoneNumberVerified(): ?bool
    {
        return $this->phoneNumberVerified;
    }

    public function getAddress(): ?array
    {
        return $this->address;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function getAdditionalClaims(): array
    {
        return $this->additionalClaims;
    }
}
