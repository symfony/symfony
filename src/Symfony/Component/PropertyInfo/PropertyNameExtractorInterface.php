<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo;

/**
 * Extracts the name of the property for the given accessor.
 */
interface PropertyNameExtractorInterface
{
    public function getPropertyName(string $class, string $method, array $context = []): ?string;
}
