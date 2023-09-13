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

final class ImportMapPackageAuditVulnerability
{
    public function __construct(
        public readonly string $ghsaId,
        public readonly ?string $cveId,
        public readonly string $url,
        public readonly string $summary,
        public readonly string $severity,
        public readonly ?string $vulnerableVersionRange,
        public readonly ?string $firstPatchedVersion,
    ) {
    }
}
