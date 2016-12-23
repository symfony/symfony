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

@trigger_error('The '.HelperInterface::class.' interface is deprecated since version 3.3 and will be removed in 4.0. Use Twig instead.', E_USER_DEPRECATED);

/**
 * HelperInterface is the interface all helpers must implement.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated The HelperInterface interface will be removed in Symfony 4.0. You should use Twig instead.
 */
interface HelperInterface
{
    /**
     * Returns the canonical name of this helper.
     *
     * @return string The canonical name
     */
    public function getName();

    /**
     * Sets the default charset.
     *
     * @param string $charset The charset
     */
    public function setCharset($charset);

    /**
     * Gets the default charset.
     *
     * @return string The default charset
     */
    public function getCharset();
}
