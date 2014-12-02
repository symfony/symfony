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

trigger_error('The "Symfony\Component\Validator\Mapping\ElementMetadata" class was deprecated in version 2.5 and will be removed in 3.0. Use "Symfony\Component\Validator\Mapping\GenericMetadata" instead.', E_USER_DEPRECATED);

/**
 * Contains the metadata of a structural element.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated Deprecated since version 2.5, to be removed in Symfony 3.0.
 *             Extend {@link GenericMetadata} instead.
 */
abstract class ElementMetadata extends GenericMetadata
{
}
