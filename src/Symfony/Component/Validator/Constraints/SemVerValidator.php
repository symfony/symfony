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
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
class SemVerValidator extends ConstraintValidator
{
    /**
     * Strict Semantic Versioning 2.0.0 regex pattern.
     * According to https://semver.org, no "v" prefix allowed
     * Supports: 1.0.0, 1.2.3, 1.2.3-alpha, 1.2.3-alpha.1, 1.2.3+20130313144700, 1.2.3-beta+exp.sha.5114f85
     */
    private const STRICT_SEMVER_PATTERN = '/^'
        .'(?P<major>0|[1-9]\d*)'                                         // Major version
        .'\.(?P<minor>0|[1-9]\d*)'                                       // Minor version
        .'\.(?P<patch>0|[1-9]\d*)'                                       // Patch version
        .'(?:-(?P<prerelease>(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)'   // Pre-release version
        .'(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?'            // Pre-release segments
        .'(?:\+(?P<buildmetadata>[0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?'   // Build metadata
        .'$/';

    /**
     * Loose semantic versioning pattern that allows partial versions.
     * Supports: 1, 1.2, 1.2.3, v1, v1.2, v1.2.3, plus all the variations above
     */
    private const LOOSE_SEMVER_PATTERN = '/^'
        .'(?P<prefix>v)?'                                                // Optional "v" prefix
        .'(?P<major>0|[1-9]\d*)'                                         // Major version (required)
        .'(?:'
        .'\.(?P<minor>0|[1-9]\d*)'                                       // Minor version
        .'(?:'
        .'\.(?P<patch>0|[1-9]\d*)'                                       // Patch version
        .'(?:-(?P<prerelease>(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)'   // Pre-release (only with full version)
        .'(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?'           // Pre-release segments
        .'(?:\+(?P<buildmetadata>[0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?'  // Build metadata (only with full version)
        .')?'
        .')?'
        .'$/';

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof SemVer) {
            throw new UnexpectedTypeException($constraint, SemVer::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_string($value) && !$value instanceof \Stringable) {
            throw new UnexpectedValueException($value, 'string');
        }

        $value = (string) $value;

        // Use strict pattern (official SemVer spec) or loose pattern (common variations)
        $pattern = $constraint->strict ? self::STRICT_SEMVER_PATTERN : self::LOOSE_SEMVER_PATTERN;
        
        if (!preg_match($pattern, $value)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(SemVer::INVALID_SEMVER_ERROR)
                ->addViolation();
        }
    }
}
