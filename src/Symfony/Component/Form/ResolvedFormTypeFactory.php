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
 *
 * @since v2.1.0
 */
class ResolvedFormTypeFactory implements ResolvedFormTypeFactoryInterface
{
    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function createResolvedType(FormTypeInterface $type, array $typeExtensions, ResolvedFormTypeInterface $parent = null)
    {
        return new ResolvedFormType($type, $typeExtensions, $parent);
    }
}
