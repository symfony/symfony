<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Mapping\Loader;

use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;

/**
 * Allows a loader to defer work until all loaders in a chain have run.
 *
 * This is useful when a loader contributes metadata that another loader may
 * provide later in the chain. Loaders that are used directly can keep their
 * immediate validation behavior.
 *
 * @author Matthias Schmidt <matthias@mttsch.dev>
 */
interface LoaderChainAwareInterface extends LoaderInterface
{
    public function prepareLoading(ClassMetadataInterface $metadata): void;

    public function finalizeLoading(ClassMetadataInterface $metadata): void;
}
