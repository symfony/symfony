<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

class DummyWithHasser
{
    private $enabled;

    public function __construct(
        public readonly ?string $url,
        private bool $active,
    ) {
    }

    public function hasUrl(): bool
    {
        return '' !== ($this->url ?? '');
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
