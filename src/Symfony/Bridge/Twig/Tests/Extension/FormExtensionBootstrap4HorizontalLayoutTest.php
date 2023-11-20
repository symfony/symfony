<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension;

use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Tests\Extension\Fixtures\StubTranslator;

/**
 * Class providing test cases for the Bootstrap 4 Twig form theme.
 *
 * @author Hidde Wieringa <hidde@hiddewieringa.nl>
 */
class FormExtensionBootstrap4HorizontalLayoutTest extends AbstractBootstrap4HorizontalLayoutTestCase
{
    protected array $testableFeatures = [
        'choice_attr',
    ];

    protected function getTemplatePaths(): array
    {
        return [
            __DIR__.'/../../Resources/views/Form',
            __DIR__.'/Fixtures/templates/form',
        ];
    }

    protected function getTwigExtensions(): array
    {
        return [
            new TranslationExtension(new StubTranslator()),
            new FormExtension(),
        ];
    }

    protected function getThemes(): array
    {
        return [
            'bootstrap_4_horizontal_layout.html.twig',
            'custom_widgets.html.twig',
        ];
    }
}
