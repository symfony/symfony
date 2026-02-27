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

    private function createRenderer(): FormRenderer
    {
        $twig = new Environment(new ArrayLoader([
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
