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

final readonly class ImportMapPackageAudit
{
    public function __construct(
        public string $package,
        public ?string $version,
        /** @var array<ImportMapPackageAuditVulnerability> */
        public array $vulnerabilities = [],
    ) {
    }

    public function withVulnerability(ImportMapPackageAuditVulnerability $vulnerability): self
    {
        return new self(
            $this->package,
            $this->version,
            [...$this->vulnerabilities, $vulnerability],
        );
    }
}
