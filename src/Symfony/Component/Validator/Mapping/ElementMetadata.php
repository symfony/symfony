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
 * Contains the metadata of a structural element.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated since version 2.5, to be removed in 3.0.
 *             Extend {@link GenericMetadata} instead.
 */
abstract class ElementMetadata extends GenericMetadata
{
    public function __construct()
    {
        if (!$this instanceof MemberMetadata && !$this instanceof ClassMetadata) {
            @trigger_error('The '.__CLASS__.' class is deprecated since Symfony 2.5 and will be removed in 3.0. Use the Symfony\Component\Validator\Mapping\GenericMetadata class instead.', E_USER_DEPRECATED);
        }
    }
}
