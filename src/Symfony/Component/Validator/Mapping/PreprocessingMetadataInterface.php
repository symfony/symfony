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
 * Interface for validation metadata that pre-processes values.
 *
 * Metadata implementing this interface is able to pre-process values before
 * they are passed on the constraint validators.
 *
 * @since  2.7
 *
 * @author Wolfgang Ziegler <fago@wolfgangziegler.net>
 *
 * @see CascadingStrategy
 * @see TraversalStrategy
 */
interface PreprocessingMetadataInterface extends MetadataInterface
{
    /**
     * Pre-processes the value before validation.
     *
     * @param mixed $value The value to be validated.
     *
     * @return mixed The pre-processed value.
     */
    public function preprocessValue($value);

}
