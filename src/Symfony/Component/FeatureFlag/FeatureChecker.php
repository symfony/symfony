<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\FeatureFlag;

use Symfony\Component\FeatureFlag\Provider\ProviderInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @experimental
 */
final class FeatureChecker implements FeatureCheckerInterface, ResetInterface
{
    private array $cache = [];

    public function __construct(
        private readonly ProviderInterface $provider,
    ) {
    }

    public function isEnabled(string $featureName): bool
    {
        return true === $this->getValue($featureName);
    }

    public function getValue(string $featureName): mixed
    {
        if (\array_key_exists($featureName, $this->cache)) {
            return $this->cache[$featureName];
        }

        $feature = $this->provider->get($featureName) ?? fn () => false;

        return $this->cache[$featureName] = $feature();
    }

    public function reset(): void
    {
        $this->cache = [];
    }
}
