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
 */
interface AdvancedNameConverterInterface extends NameConverterInterface
{
    /**
     * {@inheritdoc}
     */
    public function normalize(string $propertyName, string $class = null, string $format = null, array $context = []): string;

    /**
     * {@inheritdoc}
     */
    public function denormalize(string $propertyName, string $class = null, string $format = null, array $context = []): string;
}
