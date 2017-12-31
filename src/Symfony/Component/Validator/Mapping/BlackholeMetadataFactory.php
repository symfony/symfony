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

@trigger_error('The '.__NAMESPACE__.'\BlackholeMetadataFactory class is deprecated since Symfony 2.5 and will be removed in 3.0. Use the Symfony\Component\Validator\Mapping\Factory\BlackHoleMetadataFactory class instead.', E_USER_DEPRECATED);

use Symfony\Component\Validator\Mapping\Factory\BlackHoleMetadataFactory as MappingBlackHoleMetadataFactory;

/**
 * Alias of {@link Factory\BlackHoleMetadataFactory}.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since version 2.5, to be removed in 3.0.
 *             Use {@link Factory\BlackHoleMetadataFactory} instead.
 */
class BlackholeMetadataFactory extends MappingBlackHoleMetadataFactory
{
}
