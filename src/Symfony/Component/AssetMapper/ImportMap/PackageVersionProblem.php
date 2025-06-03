<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\ImportMap;

final class PackageVersionProblem
{
    public function __construct(
        public readonly string $packageName,
        public readonly string $dependencyPackageName,
        public readonly string $requiredVersionConstraint,
        public readonly ?string $installedVersion,
    ) {
    }
}
