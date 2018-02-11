<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Debug;

/**
 * Interface for classes that can provide info about the purpose of service classes/interfaces.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
interface AutowiringInfoProviderInterface
{
    /**
     * Returns information about autowiring types.
     *
     * @return array|AutowiringTypeInfo[]
     */
    public function getTypeInfos(): array;
}
