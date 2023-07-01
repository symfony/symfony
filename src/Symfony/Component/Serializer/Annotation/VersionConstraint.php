<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Annotation;

use Symfony\Component\Serializer\Exception\InvalidArgumentException;

/**
 * Annotation class for @VersionConstraint().
 *
 * @Annotation
 *
 * @NamedArgumentConstructor
 *
 * @Target({"PROPERTY", "METHOD"})
 *
 * @author Olivier Michaud <olivier@micoli.org>
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_PROPERTY)]
final class VersionConstraint
{
    public function __construct(private readonly ?string $since = null, private readonly ?string $until = null)
    {
        if ('' === $since) {
            throw new InvalidArgumentException(sprintf('Parameter "since" of annotation "%s" must be a non-empty string.', self::class));
        }
        if ('' === $until) {
            throw new InvalidArgumentException(sprintf('Parameter "until" of annotation "%s" must be a non-empty string.', self::class));
        }
        if (null === $since && null === $until) {
            throw new InvalidArgumentException(sprintf('At least one of "since" or "until" properties of annotation "%s" have to be defined.', self::class));
        }
    }

    public function isVersionCompatible(string $version): bool
    {
        if ($this->since) {
            if (!version_compare($version, $this->since, '>=')) {
                return false;
            }
        }
        if ($this->until) {
            if (!version_compare($version, $this->until, '<=')) {
                return false;
            }
        }

        return true;
    }
}
