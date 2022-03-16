<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Extractor;

/**
 * @author David Maicher <mail@dmaicher.de>
 */
interface ObjectPropertyListExtractorInterface
{
    /**
     * Gets the list of properties available for the given object.
     *
     * @return string[]|null
     */
    public function getProperties(object $object, array $context = []): ?array;
}
