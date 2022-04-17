<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\AbstractRendererEngine;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\FormView;

class FormRendererTest extends TestCase
{
    public function testHumanize()
    {
        $renderer = new FormRenderer(new DummyFormRendererEngine());

        $this->assertEquals('Is active', $renderer->humanize('is_active'));
        $this->assertEquals('Is active', $renderer->humanize('isActive'));
    }
}

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
