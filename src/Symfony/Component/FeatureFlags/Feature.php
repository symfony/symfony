<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\FeatureFlags;

use Symfony\Component\FeatureFlags\Strategy\StrategyInterface;

final class Feature
{
    public function __construct(
        private readonly string $name,
        private readonly string $description,
        private readonly bool $default,
        private readonly StrategyInterface $strategy,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function isEnabled(): bool
    {
        return match($this->strategy->compute()) {
            StrategyResult::Grant => true,
            StrategyResult::Deny => false,
            StrategyResult::Abstain => $this->default,
        };
    }
}
