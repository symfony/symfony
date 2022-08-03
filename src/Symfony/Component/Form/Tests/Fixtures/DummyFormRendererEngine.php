<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Fixtures;

use Symfony\Component\Form\AbstractRendererEngine;
use Symfony\Component\Form\FormView;

class DummyFormRendererEngine extends AbstractRendererEngine
{
    public function renderBlock(FormView $view, $resource, $blockName, array $variables = []): string
    {
        return '';
    }

    protected function loadResourceForBlockName($cacheKey, FormView $view, $blockName): bool
    {
        return true;
    }
}
