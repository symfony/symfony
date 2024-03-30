<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\LogicException;

/**
 * Validates that the given string does not contain characters used in spoofing security attacks.
 *
 * @see https://www.php.net/manual/en/class.spoofchecker.php
 *
 * @author Mathieu Lechat <mathieu.lechat@les-tilleuls.coop>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class NoSuspiciousCharacters extends Constraint
{
    public const RESTRICTION_LEVEL_ERROR = '1ece07dc-dca2-45f1-ba47-8d7dc3a12774';
    public const INVISIBLE_ERROR = '6ed60e6c-179b-4e93-8a6c-667d85c6de5e';
    public const MIXED_NUMBERS_ERROR = '9f01fc26-3bc4-44b1-a6b1-c08e2412053a';
    public const HIDDEN_OVERLAY_ERROR = '56380dc5-0476-4f04-bbaa-b68cd1c2d974';

    protected const ERROR_NAMES = [
        self::RESTRICTION_LEVEL_ERROR => 'RESTRICTION_LEVEL_ERROR',
        self::INVISIBLE_ERROR => 'INVISIBLE_ERROR',
        self::MIXED_NUMBERS_ERROR => 'MIXED_NUMBERS_ERROR',
        self::HIDDEN_OVERLAY_ERROR => 'INVALID_CASE_ERROR',
    ];

    /**
     * Check a string for the presence of invisible characters such as zero-width spaces,
     * or character sequences that are likely not to display such as multiple occurrences of the same non-spacing mark.
     */
    public const CHECK_INVISIBLE = 32;

    /**
     * Check that a string does not mix numbers from different numbering systems;
     * for example “8” (Digit Eight) and “৪” (Bengali Digit Four).
     */
    public const CHECK_MIXED_NUMBERS = 128;

    /**
     * Check that a string does not have a combining character following a character in which it would be hidden;
     * for example “i” (Latin Small Letter I) followed by a U+0307 (Combining Dot Above).
     */
    public const CHECK_HIDDEN_OVERLAY = 256;

    /** @see https://unicode.org/reports/tr39/#ascii_only */
    public const RESTRICTION_LEVEL_ASCII = 268435456;

    /** @see https://unicode.org/reports/tr39/#single_script */
    public const RESTRICTION_LEVEL_SINGLE_SCRIPT = 536870912;

    /** @see https://unicode.org/reports/tr39/#highly_restrictive */
    public const RESTRICTION_LEVEL_HIGH = 805306368;

    /** @see https://unicode.org/reports/tr39/#moderately_restrictive */
    public const RESTRICTION_LEVEL_MODERATE = 1073741824;

    /** @see https://unicode.org/reports/tr39/#minimally_restrictive */
    public const RESTRICTION_LEVEL_MINIMAL = 1342177280;

    /** @see https://unicode.org/reports/tr39/#unrestricted */
    public const RESTRICTION_LEVEL_NONE = 1610612736;

    public string $restrictionLevelMessage = 'This value contains characters that are not allowed by the current restriction-level.';
    public string $invisibleMessage = 'Using invisible characters is not allowed.';
    public string $mixedNumbersMessage = 'Mixing numbers from different scripts is not allowed.';
    public string $hiddenOverlayMessage = 'Using hidden overlay characters is not allowed.';

    public int $checks = self::CHECK_INVISIBLE | self::CHECK_MIXED_NUMBERS | self::CHECK_HIDDEN_OVERLAY;
    public ?int $restrictionLevel = null;
    public ?array $locales = null;

    /**
     * @param array<string,mixed>|null                    $options
     * @param int-mask-of<self::CHECK_*>|null             $checks           A bitmask of the checks to perform on the string (defaults to all checks)
     * @param int-mask-of<self::RESTRICTION_LEVEL_*>|null $restrictionLevel Configures the set of acceptable characters for the validated string through a specified "level" (defaults to
     *                                                                      {@see NoSuspiciousCharacters::RESTRICTION_LEVEL_MODERATE} on ICU >= 58, {@see NoSuspiciousCharacters::RESTRICTION_LEVEL_SINGLE_SCRIPT} otherwise)
     * @param string[]|null                               $locales          Restrict the string's characters to those normally used with these locales. Pass null to use the default locales configured for the NoSuspiciousCharactersValidator. (defaults to null)
     * @param string[]|null                               $groups
     */
    public function __construct(
        ?array $options = null,
        ?string $restrictionLevelMessage = null,
        ?string $invisibleMessage = null,
        ?string $mixedNumbersMessage = null,
        ?string $hiddenOverlayMessage = null,
        ?int $checks = null,
        ?int $restrictionLevel = null,
        ?array $locales = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        if (!class_exists(\Spoofchecker::class)) {
            throw new LogicException('The intl extension is required to use the NoSuspiciousCharacters constraint.');
        }

        parent::__construct($options, $groups, $payload);

        $this->restrictionLevelMessage = $restrictionLevelMessage ?? $this->restrictionLevelMessage;
        $this->invisibleMessage = $invisibleMessage ?? $this->invisibleMessage;
        $this->mixedNumbersMessage = $mixedNumbersMessage ?? $this->mixedNumbersMessage;
        $this->hiddenOverlayMessage = $hiddenOverlayMessage ?? $this->hiddenOverlayMessage;
        $this->checks = $checks ?? $this->checks;
        $this->restrictionLevel = $restrictionLevel ?? $this->restrictionLevel;
        $this->locales = $locales ?? $this->locales;
    }
}
