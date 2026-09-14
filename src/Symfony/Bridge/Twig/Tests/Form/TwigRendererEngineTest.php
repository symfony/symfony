<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Form;

use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\Test\FormIntegrationTestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class TwigRendererEngineTest extends FormIntegrationTestCase
{
    public function testNestedCollectionsDoNotReuseParentBlockPrefixCache()
    {
        $renderer = $this->createRenderer();

        $form = $this->factory->create(TaskManagerType::class);
        $form->submit([
            'taskLists' => [
                [
                    'tasks' => [
                        ['name' => 'first'],
                    ],
                ],
            ],
        ]);

        $view = $form->createView();
        $renderer->setTheme($view, 'form_layout.html.twig');

        $this->assertSame('[embedded-row]', $renderer->searchAndRenderBlock($view['taskLists'][0], 'row'));
        $this->assertSame('[form-row]', $renderer->searchAndRenderBlock($view['taskLists'][0]['tasks'][0], 'row'));
    }

    public function testBlocksAreComposedAcrossTheWholeThemeStack()
    {
        $renderer = $this->createRenderer([
            'form_layout.html.twig' => '{% block form_row %}[row:{{ block("form_widget") }}]{% endblock %}{% block form_widget %}[default-widget]{% endblock %}',
            'custom_layout.html.twig' => '{% block form_widget %}[custom-widget]{% endblock %}',
        ]);

        $view = $this->factory->create(TaskType::class)->createView();
        $renderer->setTheme($view, 'custom_layout.html.twig');

        $this->assertSame('[row:[custom-widget]]', $renderer->searchAndRenderBlock($view, 'row'));
    }

    public function testTheLastThemeWins()
    {
        $renderer = $this->createRenderer([
            'form_layout.html.twig' => '{% block form_row %}[default-row]{% endblock %}',
            'first_layout.html.twig' => '{% block form_row %}[first-row]{% endblock %}',
            'second_layout.html.twig' => '{% block form_row %}[second-row]{% endblock %}',
        ]);

        $view = $this->factory->create(TaskType::class)->createView();
        $renderer->setTheme($view, ['first_layout.html.twig', 'second_layout.html.twig']);

        $this->assertSame('[second-row]', $renderer->searchAndRenderBlock($view, 'row'));
    }

    public function testAViewCombinesItsOwnThemesWithTheOnesItInherits()
    {
        $renderer = $this->createRenderer([
            'form_layout.html.twig' => '{% block form_label %}[default-label]{% endblock %}{% block form_errors %}[default-errors]{% endblock %}{% block form_help %}[default-help]{% endblock %}',
            'parent_layout.html.twig' => '{% block form_label %}[parent-label]{% endblock %}{% block form_errors %}[parent-errors]{% endblock %}',
            'child_layout.html.twig' => '{% block form_label %}[child-label]{% endblock %}',
        ]);

        $view = $this->factory->create(TaskType::class)->createView();
        $renderer->setTheme($view, 'parent_layout.html.twig');
        $renderer->setTheme($view['name'], 'child_layout.html.twig');

        $this->assertSame('[child-label]', $renderer->searchAndRenderBlock($view['name'], 'label'));
        $this->assertSame('[parent-errors]', $renderer->searchAndRenderBlock($view['name'], 'errors'));
        $this->assertSame('[default-help]', $renderer->searchAndRenderBlock($view['name'], 'help'));

        $this->assertSame('[parent-label]', $renderer->searchAndRenderBlock($view, 'label'));
    }

    public function testDefaultThemesAreDiscardedWhenNotUsed()
    {
        $renderer = $this->createRenderer([
            'form_layout.html.twig' => '{% block form_row %}[default-row]{% endblock %}{% block form_widget %}[default-widget]{% endblock %}',
            'custom_layout.html.twig' => '{% block form_row %}[custom-row]{% endblock %}',
        ]);

        $view = $this->factory->create(TaskType::class)->createView();
        $renderer->setTheme($view, 'custom_layout.html.twig', false);

        $this->assertSame('[custom-row]', $renderer->searchAndRenderBlock($view, 'row'));

        $this->expectException(LogicException::class);
        $renderer->searchAndRenderBlock($view['name'], 'widget');
    }

    public function testNoBlockIsFoundWhenAViewHasNeitherOwnNorDefaultThemes()
    {
        $renderer = $this->createRenderer([
            'form_layout.html.twig' => '{% block form_row %}[default-row]{% endblock %}',
        ]);

        $view = $this->factory->create(TaskType::class)->createView();
        $renderer->setTheme($view, [], false);

        $this->expectException(LogicException::class);
        $renderer->searchAndRenderBlock($view, 'row');
    }

    public function testThemesCanBeChangedAfterABlockHasBeenLoaded()
    {
        $twig = new Environment(new ArrayLoader([
            'form_layout.html.twig' => '{% block form_row %}[default-row]{% endblock %}',
            'first_layout.html.twig' => '{% block form_row %}[first-row]{% endblock %}',
            'second_layout.html.twig' => '{% block form_row %}[second-row]{% endblock %}',
        ]));
        $engine = new TwigRendererEngine(['form_layout.html.twig'], $twig);

        $view = $this->factory->create(TaskType::class)->createView();

        $engine->setTheme($view, ['first_layout.html.twig']);
        $this->assertSame('[first-row]', $engine->renderBlock($view, $engine->getResourceForBlockName($view, 'form_row'), 'form_row'));

        $engine->setTheme($view, ['second_layout.html.twig']);
        $this->assertSame('[second-row]', $engine->renderBlock($view, $engine->getResourceForBlockName($view, 'form_row'), 'form_row'));
    }

    private function createRenderer(?array $templates = null): FormRenderer
    {
        $twig = new Environment(new ArrayLoader($templates ?? [
            'form_layout.html.twig' => <<<'TWIG'
                {% block form_row %}[form-row]{% endblock %}
                {% block embedded_row %}[embedded-row]{% endblock %}
                TWIG,
        ]));

        $engine = new TwigRendererEngine(['form_layout.html.twig'], $twig);

        return new FormRenderer($engine);
    }
}

class TaskManagerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('taskLists', CollectionType::class, [
            'entry_type' => TaskListType::class,
            'allow_add' => true,
        ]);
    }
}

class TaskListType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('tasks', CollectionType::class, [
            'entry_type' => TaskType::class,
            'allow_add' => true,
        ]);
    }

    public function getParent(): ?string
    {
        return EmbeddedType::class;
    }
}

class TaskType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class);
    }
}

class EmbeddedType extends AbstractType
{
    public function getParent(): ?string
    {
        return FormType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'embedded';
    }
}
