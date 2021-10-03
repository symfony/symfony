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
 * Creates ResolvedFormTypeInterface instances.
 *
 * This interface allows you to use your custom ResolvedFormTypeInterface
 * implementation, within which you can customize the concrete FormBuilderInterface
 * implementations or FormView subclasses that are used by the framework.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface ResolvedFormTypeFactoryInterface
{
    /**
     * Resolves a form type.
     *
     * @param FormTypeExtensionInterface[] $typeExtensions
     *
     * @return ResolvedFormTypeInterface
     *
     * @throws Exception\UnexpectedTypeException  if the types parent {@link FormTypeInterface::getParent()} is not a string
     * @throws Exception\InvalidArgumentException if the types parent cannot be retrieved from any extension
     */
    public function createResolvedType(FormTypeInterface $type, array $typeExtensions, ResolvedFormTypeInterface $parent = null);
}
