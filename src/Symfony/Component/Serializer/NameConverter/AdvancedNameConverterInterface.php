<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\NameConverter;

/**
 * Gives access to the class, the format and the context in the property name converters.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 *
 * @deprecated since Symfony 7.2, use NameConverterInterface instead
 */
interface AdvancedNameConverterInterface extends NameConverterInterface
{
    public function normalize(string $propertyName, ?string $class = null, ?string $format = null, array $context = []): string;

    public function denormalize(string $propertyName, ?string $class = null, ?string $format = null, array $context = []): string;
}
