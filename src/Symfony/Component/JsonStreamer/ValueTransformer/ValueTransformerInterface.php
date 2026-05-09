<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\ValueTransformer;

use Symfony\Component\JsonStreamer\Transformer\PropertyValueTransformerInterface;

trigger_deprecation('symfony/json-streamer', '8.1', 'The "%s" interface is deprecated, use "%s" instead.', ValueTransformerInterface::class, PropertyValueTransformerInterface::class);

/**
 * Transforms a native value before stream writing and after stream reading.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @deprecated since Symfony 8.1, use Symfony\Component\JsonStreamer\Transformer\PropertyValueTransformerInterface instead
 */
interface ValueTransformerInterface extends PropertyValueTransformerInterface
{
}
