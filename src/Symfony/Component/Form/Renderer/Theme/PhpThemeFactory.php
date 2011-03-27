<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Renderer\Theme;

use Symfony\Component\Form\Exception\FormException;

/**
 * Creates PhpTheme instances
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class PhpThemeFactory implements ThemeFactoryInterface
{
    /**
     * @var string
     */
    private $charset;

    public function __construct($charset = 'UTF-8')
    {
        $this->charset = $charset;
    }

    /**
     * @see Symfony\Component\Form\Renderer\Theme\ThemeFactoryInterface::create()
     */
    public function create($template = null)
    {
        if ($template !== null) {
            throw new FormException('PHP don\'t accept templates');
        }

        return new PhpTheme($this->charset);
    }
}