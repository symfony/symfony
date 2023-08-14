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

trigger_deprecation('symfony/templating', '6.4', '"%s" is deprecated since version 6.4 and will be removed in 7.0. Use Twig instead.', Helper::class);

/**
 * Helper is the base class for all helper classes.
 *
 * Most of the time, a Helper is an adapter around an existing
 * class that exposes a read-only interface for templates.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since Symfony 6.4, use Twig instead
 */
abstract class Helper implements HelperInterface
{
    protected $charset = 'UTF-8';

    /**
     * Sets the default charset.
     *
     * @return void
     */
    public function setCharset(string $charset)
    {
        $this->charset = $charset;
    }

    /**
     * Gets the default charset.
     */
    public function getCharset(): string
    {
        return $this->charset;
    }
}
