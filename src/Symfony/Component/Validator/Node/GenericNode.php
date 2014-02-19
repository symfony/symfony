<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Node;

/**
 * Represents a value that has neither class metadata nor property metadata
 * attached to it.
 *
 * Together with {@link \Symfony\Component\Validator\Mapping\GenericMetadata},
 * this node type can be used to validate a value against some given
 * constraints.
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class GenericNode extends Node
{
}
