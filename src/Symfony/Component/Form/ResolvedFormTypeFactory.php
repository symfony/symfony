<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ResolvedFormTypeFactory implements ResolvedFormTypeFactoryInterface
{
    public function createResolvedType(FormTypeInterface $type, array $typeExtensions, ResolvedFormTypeInterface $parent = null): ResolvedFormTypeInterface
    {
        return new ResolvedFormType($type, $typeExtensions, $parent);
    }
}
