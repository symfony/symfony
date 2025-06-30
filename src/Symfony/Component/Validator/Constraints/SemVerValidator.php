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
     * Semantic Versioning 2.0.0 regex pattern.
     * Supports: 1.0.0, 1.2.3, 1.2.3-alpha, 1.2.3-alpha.1, 1.2.3+20130313144700, 1.2.3-beta+exp.sha.5114f85
     * With optional "v" prefix: v1.0.0, v1.2.3-alpha
     */
    private const SEMVER_PATTERN = '/^(?P<prefix>v)?(?P<major>0|[1-9]\d*)\.(?P<minor>0|[1-9]\d*)\.(?P<patch>0|[1-9]\d*)(?:-(?P<prerelease>(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?(?:\+(?P<buildmetadata>[0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$/';

    /**
     * Loose semantic versioning pattern that allows partial versions.
     * Supports: 1, 1.2, 1.2.3, v1, v1.2, v1.2.3, plus all the variations above
     */
    private const LOOSE_SEMVER_PATTERN = '/^(?P<prefix>v)?(?P<major>0|[1-9]\d*)(?:\.(?P<minor>0|[1-9]\d*)(?:\.(?P<patch>0|[1-9]\d*))?)?(?:-(?P<prerelease>(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?(?:\+(?P<buildmetadata>[0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$/';

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

        // Use loose pattern by default to allow partial versions
        if (!preg_match(self::LOOSE_SEMVER_PATTERN, $value, $matches)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(SemVer::INVALID_SEMVER_ERROR)
                ->addViolation();

            return;
        }

        // Check prefix requirement
        if ($constraint->requirePrefix && empty($matches['prefix'])) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(SemVer::INVALID_SEMVER_ERROR)
                ->addViolation();

            return;
        }

        // Check pre-release
        if (!$constraint->allowPreRelease && !empty($matches['prerelease'])) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(SemVer::INVALID_SEMVER_ERROR)
                ->addViolation();

            return;
        }

        // Check build metadata
        if (!$constraint->allowBuildMetadata && !empty($matches['buildmetadata'])) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(SemVer::INVALID_SEMVER_ERROR)
                ->addViolation();
        }
    }
}