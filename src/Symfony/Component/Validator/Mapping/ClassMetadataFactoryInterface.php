<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Mapping;

/**
 * A factory for {@link ClassMetadata} objects.
 *
 * @deprecated Deprecated since version 2.2, to be removed in 2.3. Implement
 *             {@link \Symfony\Component\Validator\MetadataFactoryInterface} instead.
 */
interface ClassMetadataFactoryInterface
{
    /**
     * Returns metadata for a given class.
     *
     * @param string $class The class name.
     *
     * @return ClassMetadata The class metadata instance.
     */
    public function getClassMetadata($class);
}
