<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Style;

use Symfony\Component\Tui\Exception\InvalidArgumentException;

/**
 * An angle value, expressible in degrees or radians.
 *
 * Used to specify the direction of a linear gradient.
 * Convention: 0° points right, 90° points down (screen coordinates).
 *
 * @experimental
 */
final class Angle
{
    private function __construct(private readonly float $radians)
    {
    }

    public static function degrees(float $degrees): self
    {
        if (!is_finite($degrees)) {
            throw new InvalidArgumentException(\sprintf('An angle must be a finite number of degrees, got "%s".', var_export($degrees, true)));
        }

        return new self($degrees * \M_PI / 180.0);
    }

    public static function radians(float $radians): self
    {
        if (!is_finite($radians)) {
            throw new InvalidArgumentException(\sprintf('An angle must be a finite number of radians, got "%s".', var_export($radians, true)));
        }

        return new self($radians);
    }

    public function toRadians(): float
    {
        return $this->radians;
    }

    public function toDegrees(): float
    {
        return $this->radians * 180.0 / \M_PI;
    }
}
