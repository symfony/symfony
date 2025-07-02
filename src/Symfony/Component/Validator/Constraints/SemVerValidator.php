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
    private const STRICT_SEMVER_PATTERN = '/^
        (?P<major>0|[1-9]\d*)                                              # Major version
        \.
        (?P<minor>0|[1-9]\d*)                                              # Minor version
        \.
        (?P<patch>0|[1-9]\d*)                                              # Patch version
        (?:
            -
            (?P<prerelease>
                (?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)                  # Pre-release identifier
                (?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*           # Additional dot-separated identifiers
            )
        )?
        (?:
            \+
            (?P<buildmetadata>[0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*)          # Build metadata
        )?
    $/x';

    /**
     * Loose semantic versioning pattern that allows partial versions.
     * Supports: 1, 1.2, 1.2.3, v1, v1.2, v1.2.3, plus all the variations above
     */
    private const LOOSE_SEMVER_PATTERN = '/^
        (?P<prefix>v)?                                                     # Optional "v" prefix
        (?P<major>0|[1-9]\d*)                                              # Major version (required)
        (?:
            \.
            (?P<minor>0|[1-9]\d*)                                          # Minor version (optional)
            (?:
                \.
                (?P<patch>0|[1-9]\d*)                                      # Patch version (optional)
                (?:
                    -
                    (?P<prerelease>
                        (?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)          # Pre-release identifier
                        (?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*   # Additional identifiers
                    )
                )?
                (?:
                    \+
                    (?P<buildmetadata>[0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*)  # Build metadata
                )?
            )?
        )?
    $/x';

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

        $pattern = $constraint->strict ? self::STRICT_SEMVER_PATTERN : self::LOOSE_SEMVER_PATTERN;
        
        if (!preg_match($pattern, $value)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(SemVer::INVALID_SEMVER_ERROR)
                ->addViolation();
            
            return;
        }

        // Normalize the version for comparison (remove 'v' prefix if present)
        $normalizedValue = $this->normalizeVersion($value);

        // Check min constraint
        if (null !== $constraint->min) {
            $normalizedMin = $this->normalizeVersion($constraint->min);
            
            if (!preg_match($pattern, $constraint->min)) {
                throw new \InvalidArgumentException(sprintf('The "min" option value "%s" is not a valid semantic version according to the "strict" option.', $constraint->min));
            }
            
            if (version_compare($normalizedValue, $normalizedMin, '<')) {
                $this->context->buildViolation($constraint->tooLowMessage)
                    ->setParameter('{{ value }}', $this->formatValue($value))
                    ->setParameter('{{ min }}', $constraint->min)
                    ->setCode(SemVer::TOO_LOW_ERROR)
                    ->addViolation();
            }
        }

        // Check max constraint
        if (null !== $constraint->max) {
            $normalizedMax = $this->normalizeVersion($constraint->max);
            
            if (!preg_match($pattern, $constraint->max)) {
                throw new \InvalidArgumentException(sprintf('The "max" option value "%s" is not a valid semantic version according to the "strict" option.', $constraint->max));
            }
            
            if (version_compare($normalizedValue, $normalizedMax, '>')) {
                $this->context->buildViolation($constraint->tooHighMessage)
                    ->setParameter('{{ value }}', $this->formatValue($value))
                    ->setParameter('{{ max }}', $constraint->max)
                    ->setCode(SemVer::TOO_HIGH_ERROR)
                    ->addViolation();
            }
        }
    }

    /**
     * Normalizes a version string for comparison by removing the 'v' prefix and
     * ensuring it has all three version components (major.minor.patch).
     */
    private function normalizeVersion(string $version): string
    {
        // Remove 'v' prefix if present
        $version = ltrim($version, 'v');
        
        // Split into parts
        $parts = explode('.', explode('-', explode('+', $version)[0])[0]);
        
        // Ensure we have at least 3 parts for version_compare
        while (count($parts) < 3) {
            $parts[] = '0';
        }
        
        // Get pre-release and build metadata if any
        $suffix = '';
        if (preg_match('/^[^-+]+(.+)$/', $version, $matches)) {
            $suffix = $matches[1];
        }
        
        return implode('.', array_slice($parts, 0, 3)) . $suffix;
    }
}
