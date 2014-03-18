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

use Symfony\Component\Validator\Mapping\Factory\LazyMetadataFactory;

/**
 * Alias of {@link LazyMetadataFactory}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated Deprecated since version 2.5, to be removed in Symfony 3.0.
 *             Use {@link LazyMetadataFactory} instead.
 */
class ClassMetadataFactory extends LazyMetadataFactory
{
}
