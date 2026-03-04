<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper;

/**
 * Provides asset paths to be registered in the asset mapper.
 *
 * Implement this interface and tag the service with "asset_mapper.path_provider"
 * to dynamically add asset paths (e.g. from glob patterns) to the asset mapper.
 *
 */
interface AssetMapperPathProviderInterface
{
    /**
     * Returns asset paths to register.
     *
     * @return array<string, string> Keys are directory paths (absolute or relative to project root),
     *                               values are namespaces (empty string for no namespace)
     */
    public function getPaths(): array;
}
