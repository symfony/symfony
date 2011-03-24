<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Renderer\Theme;

use Symfony\Component\Form\Renderer\Theme\PhpThemeFactory;

/**
 * Test theme template files shipped with framework bundle.
 */
class PhpThemeFunctionalTest extends AbstractThemeFunctionalTest
{
    protected function createThemeFactory()
    {
        return new PhpThemeFactory();
    }
}