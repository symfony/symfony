<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Renderer\ThemeEngine;

use Symfony\Component\Form\Renderer\ThemeEngine\PhpThemeEngine;

class PhpThemeEngineTest extends AbstractThemeEngineTest
{
    protected function createEngine()
    {
        return new PhpThemeEngine();
    }
}