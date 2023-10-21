<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating\Helper;

trigger_deprecation('symfony/templating', '6.4', '"%s" is deprecated since version 6.4 and will be removed in 7.0. Use Twig instead.', HelperInterface::class);

/**
 * HelperInterface is the interface all helpers must implement.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since Symfony 6.4, use Twig instead
 */
interface HelperInterface
{
    /**
     * Returns the canonical name of this helper.
     *
     * @return string
     */
    public function getName();

    /**
     * Sets the default charset.
     *
     * @return void
     */
    public function setCharset(string $charset);

    /**
     * Gets the default charset.
     */
    public function getCharset(): string;
}
