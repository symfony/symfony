<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Mapping\Read;

use Symfony\Component\JsonStreamer\Mapping\PropertyMetadataLoaderInterface;

trigger_deprecation('symfony/json-streamer', '8.1', 'The "%s" class is deprecated, Date times are handled as value objects.', DateTimeTypePropertyMetadataLoader::class);

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @deprecated since Symfony 8.1, Date times are handled as value objects
 *
 * @internal
 */
final class DateTimeTypePropertyMetadataLoader implements PropertyMetadataLoaderInterface
{
    public function __construct(
        private PropertyMetadataLoaderInterface $decorated,
    ) {
    }

    public function load(string $className, array $options = [], array $context = []): array
    {
        return $this->decorated->load($className, $options, $context);
    }
}
