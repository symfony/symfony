<?php

namespace Symfony\Component\AccessControl\Voter\ABAC;

/**
 * @experimental
 */
enum AuthenticationState: string
{
    case IS_AUTHENTICATED_FULLY = 'IS_AUTHENTICATED_FULLY';
    case IS_AUTHENTICATED_REMEMBERED = 'IS_AUTHENTICATED_REMEMBERED';
    case IS_AUTHENTICATED = 'IS_AUTHENTICATED';
    case IS_IMPERSONATOR = 'IS_IMPERSONATOR';
    case IS_REMEMBERED = 'IS_REMEMBERED';
    case PUBLIC_ACCESS = 'PUBLIC_ACCESS';

    /**
     * @return list<string>
     */
    public static function caseNames(): array
    {
        return array_map(static fn (AuthenticationState $state):string => $state->value, self::cases());
    }

    public static function fromValue(string $state): ?AuthenticationState
    {
        return match ($state) {
            'IS_AUTHENTICATED_FULLY' => self::IS_AUTHENTICATED_FULLY,
            'IS_AUTHENTICATED_REMEMBERED' => self::IS_AUTHENTICATED_REMEMBERED,
            'IS_AUTHENTICATED' => self::IS_AUTHENTICATED,
            'IS_IMPERSONATOR' => self::IS_IMPERSONATOR,
            'IS_REMEMBERED' => self::IS_REMEMBERED,
            'PUBLIC_ACCESS' => self::PUBLIC_ACCESS,
            default => null,
        };
    }
}

