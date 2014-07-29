<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OptionsResolver;

/**
 * @author Wouter J <wouter@wouterj.nl>
 */
interface NestableOptionsResolverInterface extends OptionsResolverInterface
{
    /**
     * Sets nested options resolvers.
     *
     * @param array $options A list of option names as keys and instances of
     *                       OptionsResolverInterface as values.
     */
    public function setNestedOptionsResolver(array $options);
}
