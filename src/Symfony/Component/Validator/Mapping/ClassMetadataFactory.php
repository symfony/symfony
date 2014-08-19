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

use Symfony\Component\Validator\Mapping\Factory\LazyLoadingMetadataFactory;

/**
 * Alias of {@link LazyLoadingMetadataFactory}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated Deprecated since version 2.5, to be removed in Symfony 3.0.
 *             Use {@link LazyLoadingMetadataFactory} instead.
 */
class ClassMetadataFactory extends LazyLoadingMetadataFactory
{
}
