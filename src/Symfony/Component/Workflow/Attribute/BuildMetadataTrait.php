<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Attribute;

use Symfony\Component\Workflow\Exception\LogicException;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 *
 * @internal
 */
trait BuildMetadataTrait
{
    /**
     * Stores the description under the "description" key of the metadata, where the dumpers read it.
     *
     * @param array<string, mixed> $metadata
     *
     * @return array<string, mixed>
     */
    private static function buildMetadata(array $metadata, ?string $description): array
    {
        if (null === $description) {
            return $metadata;
        }
        if (\array_key_exists('description', $metadata)) {
            throw new LogicException(\sprintf('The "description" argument of "#[%s]" cannot be used together with a "description" key in its "metadata" argument.', self::class));
        }

        return [...$metadata, 'description' => $description];
    }
}
