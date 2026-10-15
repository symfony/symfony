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

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Form\TraceableTwigRendererEngine;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Bridge\Twig\Node\SearchAndRenderBlockNode;
use Symfony\Bridge\Twig\Test\Traits\RuntimeLoaderProvider;
use Symfony\Bridge\Twig\Tests\Extension\Fixtures\StubTranslator;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\DataCollector\DataCollectorExtension;
use Symfony\Component\Form\Extension\DataCollector\FormDataCollector;
use Symfony\Component\Form\Extension\DataCollector\FormDataCollectorInterface;
use Symfony\Component\Form\Extension\DataCollector\FormDataExtractor;
use Symfony\Component\Form\Extension\DataCollector\Proxy\ResolvedTypeFactoryDataCollectorProxy;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\FormRendererEngineInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\ResolvedFormTypeFactory;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Extension\StringLoaderExtension;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

class TraceableTwigRendererEngineTest extends TestCase
{
    use RuntimeLoaderProvider;

    private const LAYOUT = <<<'TWIG'
        {% block form_start %}<form>{% endblock %}
        {% block form_end %}{{ form_rest(form) }}</form>{% endblock %}
        {% block form_rest %}{% for child in form %}{% if not child.rendered %}{{ form_row(child) }}{% endif %}{% endfor %}{% endblock %}
        {% block form_row %}[{{ form_label(form) }}{{ form_widget(form) }}]{% endblock %}
        {% block form_label %}{{ name }}:{% endblock %}
        {% block form_widget %}{% if compound %}{{ block('form_rows') }}{{ form_rest(form) }}{% else %}<{{ value }}>{% endif %}{% endblock %}
        {% block form_rows %}{% for child in form %}{% if not child.rendered %}{{ form_row(child) }}{% endif %}{% endfor %}{% endblock %}
        TWIG;

    private FormDataCollector $collector;
    private FormFactoryInterface $factory;
    private Environment $environment;

    protected function setUp(): void
    {
        if (!method_exists(FormDataCollector::class, 'collectRenderingTrace')) {
            $this->markTestSkipped('The Form component does not collect rendering traces.');
        }

        $this->collector = new FormDataCollector(new FormDataExtractor());
        $this->factory = Forms::createFormFactoryBuilder()
            ->addExtension(new DataCollectorExtension($this->collector))
            ->setResolvedTypeFactory(new ResolvedTypeFactoryDataCollectorProxy(new ResolvedFormTypeFactory(), $this->collector))
            ->getFormFactory();
    }

    public function testEachBlockIsTracedWithItsCandidatesAndTheTemplateDefiningIt()
    {
        $html = $this->render('{{ form_row(form.email) }}', $this->createUserForm()->createView());

        $this->assertSame('[email:<a@b.c>]', $html);
        $this->assertSame([
            'blocks' => [
                'label' => [
                    'hierarchy' => ['form_label', 'text_label', 'email_label', '_user_email_label'],
                    'matched' => [['block' => 'form_label', 'template' => 'layout.html.twig', 'path' => null, 'line' => 5]],
                ],
                'widget' => [
                    'hierarchy' => ['form_widget', 'text_widget', 'email_widget', '_user_email_widget'],
                    'matched' => [['block' => 'form_widget', 'template' => 'layout.html.twig', 'path' => null, 'line' => 6]],
                ],
                'row' => [
                    'hierarchy' => ['form_row', 'text_row', 'email_row', '_user_email_row'],
                    'matched' => [['block' => 'form_row', 'template' => 'layout.html.twig', 'path' => null, 'line' => 4]],
                ],
            ],
        ], $this->getRendering('user', 'email'));
    }

    public function testATypeLevelBlockOfAThemeSetOnTheParentView()
    {
        $html = $this->render("{% form_theme form 'custom.html.twig' %}{{ form_widget(form.email) }}", $this->createUserForm()->createView(), [
            'custom.html.twig' => "\n{% block email_widget %}(email){% endblock %}",
        ]);

        $this->assertSame('(email)', $html);
        $this->assertSame([['block' => 'email_widget', 'template' => 'custom.html.twig', 'path' => null, 'line' => 2]], $this->getRendering('user', 'email')['blocks']['widget']['matched']);
    }

    public function testAFieldLevelBlockOfTheCurrentTemplate()
    {
        $html = $this->render("{% extends 'base.html.twig' %}{% form_theme form _self %}{% block _user_email_widget %}(field){% endblock %}{% block body %}{{ form_widget(form.email) }}{% endblock %}", $this->createUserForm()->createView(), [
            'base.html.twig' => '{% block body %}{% endblock %}',
        ]);

        $this->assertSame('(field)', $html);

        $matched = $this->getRendering('user', 'email')['blocks']['widget']['matched'];
        $this->assertSame('_user_email_widget', $matched[0]['block']);
        $this->assertSame('page.html.twig', $matched[0]['template']);
    }

    public function testATemplateObjectUsedAsThemeDoesNotBreakRendering()
    {
        $template = "{% form_theme form template_from_string('{% block email_widget %}(string){% endblock %}') %}{{ form_widget(form.email) }}";

        $plain = $this->render($template, $this->createUserForm()->createView(), [], ['layout.html.twig'], false);
        $traced = $this->render($template, $this->createUserForm()->createView());

        $this->assertSame('(string)', $plain);
        $this->assertSame($plain, $traced);

        $matched = $this->getRendering('user', 'email')['blocks']['widget']['matched'][0];
        $this->assertSame('email_widget', $matched['block']);
        $this->assertStringStartsWith('__string_template__', $matched['template']);
        $this->assertNull($matched['path']);
        $this->assertNull($matched['line']);
    }

    public function testTheLineOfTheBlockTagIsFoundWithTheTwigLexer()
    {
        $this->render("{% form_theme form 'custom.html.twig' %}{{ form_widget(form.email) }}", $this->createUserForm()->createView(), [
            'custom.html.twig' => "{# {% block email_widget %} #}\n\n{%~ block email_widget %}(email){% endblock %}",
        ]);

        $this->assertSame(3, $this->getRendering('user', 'email')['blocks']['widget']['matched'][0]['line']);
    }

    public function testTheSourceIsReadFromTheCompiledTemplateInDebugMode()
    {
        $this->render("{% form_theme form template_from_string('\n\n{% block email_widget %}(string){% endblock %}') %}{{ form_widget(form.email) }}", $this->createUserForm()->createView(), [], ['layout.html.twig'], true, ['debug' => true]);

        $this->assertSame(3, $this->getRendering('user', 'email')['blocks']['widget']['matched'][0]['line']);
    }

    public function testABlockImportedUnderAnotherNameReportsTheLineOfTheOriginalBlock()
    {
        $this->render("{% form_theme form 'custom.html.twig' %}{{ form_widget(form.email) }}", $this->createUserForm()->createView(), [
            'custom.html.twig' => "{% use 'used.html.twig' with foo as email_widget %}",
            'used.html.twig' => "\n{% block foo %}(foo){% endblock %}",
        ]);

        $matched = $this->getRendering('user', 'email')['blocks']['widget']['matched'][0];
        $this->assertSame('used.html.twig', $matched['template']);
        $this->assertSame(2, $matched['line']);
    }

    public function testAThemeSetAfterABlockWasRenderedIsUsedForTheNextBlocks()
    {
        $this->render("{{ form_label(form.email) }}{% form_theme form 'custom.html.twig' %}{{ form_widget(form.email) }}", $this->createUserForm()->createView(), [
            'custom.html.twig' => '{% block form_widget %}(custom){% endblock %}',
        ]);

        $blocks = $this->getRendering('user', 'email')['blocks'];
        $this->assertSame('layout.html.twig', $blocks['label']['matched'][0]['template']);
        $this->assertSame('custom.html.twig', $blocks['widget']['matched'][0]['template']);
    }

    public function testARawTemplateThemeIsResolved()
    {
        [$renderer, $environment] = $this->createRendererWithStubEngine(['custom.html.twig' => '{% block _user_email_widget %}{% endblock %}']);

        $view = $this->createUserForm()->createView();
        $renderer->setTheme($view, $environment->load('custom.html.twig')->unwrap($environment));
        $renderer->searchAndRenderBlock($view['email'], 'widget');

        $this->assertSame('custom.html.twig', $this->getRendering('user', 'email')['blocks']['widget']['matched'][0]['template']);
    }

    public function testAnUnsupportedThemeGivesAnUnknownDefiner()
    {
        [$renderer] = $this->createRendererWithStubEngine([]);

        $view = $this->createUserForm()->createView();
        $renderer->setTheme($view, 42);
        $renderer->searchAndRenderBlock($view['email'], 'widget');

        $this->assertSame(['block' => '_user_email_widget', 'template' => null, 'path' => null, 'line' => null], $this->getRendering('user', 'email')['blocks']['widget']['matched'][0]);
    }

    public function testACollectorWithoutRenderingTraceSupportIsLeftAlone()
    {
        $template = "{% form_theme form 'custom.html.twig' %}{{ form_widget(form.email) }}";
        $templates = ['custom.html.twig' => '{% block email_widget %}(email){% endblock %}'];
        $plain = $this->render($template, $this->createUserForm()->createView(), $templates, ['layout.html.twig'], false);

        $this->createEngine(['page.html.twig' => $template] + $templates, ['layout.html.twig'], true, [], $this->createStub(FormDataCollectorInterface::class));

        $this->assertSame($plain, $this->environment->render('page.html.twig', ['form' => $this->createUserForm()->createView()]));
    }

    public function testResetIsForwardedToTheInnerEngine()
    {
        $inner = new StubRendererEngine();
        $engine = new TraceableTwigRendererEngine($inner, new Environment(new ArrayLoader()), [], $this->collector);

        $engine->reset();

        $this->assertSame(1, $inner->resets);
    }

    public function testThemeSetOnTheViewItself()
    {
        $html = $this->render("{% form_theme form.email 'custom.html.twig' %}{{ form_widget(form.email) }}{{ form_widget(form.name) }}", $this->createUserForm()->createView(), [
            'custom.html.twig' => '{% block form_widget %}(custom){% endblock %}',
        ]);

        $this->assertSame('(custom)<>', $html);
        $this->assertSame('custom.html.twig', $this->getRendering('user', 'email')['blocks']['widget']['matched'][0]['template']);
        $this->assertSame('layout.html.twig', $this->getRendering('user', 'name')['blocks']['widget']['matched'][0]['template']);
    }

    public function testABlockComingFromAUsedTemplateIsReportedInThatTemplate()
    {
        $this->render("{% form_theme form 'custom.html.twig' %}{{ form_widget(form.email) }}", $this->createUserForm()->createView(), [
            'custom.html.twig' => "{% use 'used.html.twig' %}",
            'used.html.twig' => '{% block email_widget %}(used){% endblock %}',
        ]);

        $this->assertSame('used.html.twig', $this->getRendering('user', 'email')['blocks']['widget']['matched'][0]['template']);
    }

    public function testABlockInheritedFromAParentThemeIsReportedInTheParent()
    {
        $this->render("{% form_theme form 'custom.html.twig' %}{{ form_widget(form.email) }}", $this->createUserForm()->createView(), [
            'custom.html.twig' => "{% extends 'parent.html.twig' %}",
            'parent.html.twig' => '{% block email_widget %}(parent){% endblock %}',
        ]);

        $this->assertSame('parent.html.twig', $this->getRendering('user', 'email')['blocks']['widget']['matched'][0]['template']);
    }

    public function testOnlyIsPassedToTheInnerEngine()
    {
        $this->expectException(RuntimeError::class);
        $this->expectExceptionMessage('Unable to render the form as none of the following blocks exist');

        $this->render("{% form_theme form with ['custom.html.twig'] only %}{{ form_label(form.email) }}", $this->createUserForm()->createView(), [
            'custom.html.twig' => '{% block form_widget %}(only){% endblock %}',
        ]);
    }

    public function testABlockCallingTheSameHelperAgainIsTracedAsADelegationChain()
    {
        $html = $this->render("{% form_theme form 'custom.html.twig' %}{{ form_widget(form.email) }}", $this->createUserForm()->createView(), [
            'custom.html.twig' => '{% block email_widget %}({{ form_widget(form) }}){% endblock %}',
        ]);

        $this->assertSame('(<a@b.c>)', $html);
        $this->assertSame([
            ['block' => 'form_widget', 'template' => 'layout.html.twig', 'path' => null, 'line' => 6],
            ['block' => 'email_widget', 'template' => 'custom.html.twig', 'path' => null, 'line' => 1],
        ], $this->getRendering('user', 'email')['blocks']['widget']['matched']);
    }

    public function testChildrenOfACompoundWidgetAreTraced()
    {
        $this->render('{{ form_widget(form) }}', $this->createUserForm()->createView());

        $this->assertSame(['label', 'widget', 'row'], array_keys($this->getRendering('user', 'email')['blocks']));
        $this->assertSame(['label', 'widget', 'row'], array_keys($this->getRendering('user', 'name')['blocks']));
        $this->assertSame(['rest', 'widget'], array_keys($this->getRendering('user')['blocks']));
    }

    public function testFieldsLeftForFormEndAreTraced()
    {
        $html = $this->render('{{ form_start(form) }}{{ form_row(form.email) }}{{ form_end(form) }}', $this->createUserForm()->createView());

        $this->assertSame('<form>[email:<a@b.c>][name:<>]</form>', $html);
        $this->assertArrayHasKey('row', $this->getRendering('user', 'name')['blocks']);

        $root = $this->getRendering('user')['blocks'];
        $this->assertSame(['form_start', 'rest', 'form_end'], array_keys($root));
        $this->assertSame(['form_end'], $root['form_end']['hierarchy']);
        $this->assertSame([['block' => 'form_end', 'template' => 'layout.html.twig', 'path' => null, 'line' => 2]], $root['form_end']['matched']);
    }

    public function testCustomPartsAreTraced()
    {
        $html = $this->render('{{ form_notice(form.email) }}', $this->createUserForm()->createView(), [
            'layout.html.twig' => self::LAYOUT."\n{% block form_notice %}(notice){% endblock %}",
        ]);

        $this->assertSame('(notice)', $html);
        $this->assertSame(['notice' => [
            'hierarchy' => ['form_notice', 'text_notice', 'email_notice', '_user_email_notice'],
            'matched' => [['block' => 'form_notice', 'template' => 'layout.html.twig', 'path' => null, 'line' => 8]],
        ]], $this->getRendering('user', 'email')['blocks']);
    }

    public function testFieldsNotRenderedHaveNoTrace()
    {
        $this->render('{{ form_label(form.email) }}', $this->createUserForm()->createView());

        $this->assertSame(['label'], array_keys($this->getRendering('user', 'email')['blocks']));
        $this->assertNull($this->getRendering('user', 'name'));
    }

    public function testFormsRenderedOneAfterTheOtherKeepTheirOwnTraces()
    {
        $templates = [
            'first.html.twig' => '{% block email_widget %}(first){% endblock %}',
            'second.html.twig' => '{% block email_widget %}(second){% endblock %}',
        ];

        foreach (['first', 'second'] as $name) {
            $view = $this->createUserForm($name)->createView();
            $this->render("{% form_theme form '".$name.".html.twig' %}{{ form_widget(form.email) }}", $view, $templates);
            unset($view);
        }

        $this->assertSame('first.html.twig', $this->getRendering('first', 'email')['blocks']['widget']['matched'][0]['template']);
        $this->assertSame('second.html.twig', $this->getRendering('second', 'email')['blocks']['widget']['matched'][0]['template']);
    }

    public function testCollectionEntriesAndPrototypeAreTraced()
    {
        $form = $this->factory->createNamedBuilder('user', FormType::class, ['tags' => ['a']])
            ->add('tags', CollectionType::class, ['entry_type' => TextType::class, 'allow_add' => true])
            ->getForm();

        $this->render('{{ form_row(form.tags) }}{{ form_row(form.tags.vars.prototype) }}', $form->createView());

        $tags = $this->collector->getData()['forms']['user']['children']['tags'];
        $this->assertSame(['form_widget', 'collection_entry_widget', 'text_widget', '_user_tags_entry_widget'], $tags['children'][0]['rendering']['blocks']['widget']['hierarchy']);
        $this->assertSame(['form_widget', 'collection_entry_widget', 'text_widget', '_user_tags_entry_widget'], $tags['prototype']['rendering']['blocks']['widget']['hierarchy']);
    }

    public function testTracingDoesNotChangeTheOutput()
    {
        $template = "{% form_theme form 'custom.html.twig' %}{{ form(form) }}";
        $templates = [
            'custom.html.twig' => "{% extends 'form_div_layout.html.twig' %}{% block email_widget %}<em>{{ block('form_widget_simple') }}</em>{% endblock %}",
        ];

        $plain = $this->render($template, $this->createUserForm()->createView(), $templates, ['form_div_layout.html.twig'], false);
        $traced = $this->render($template, $this->createUserForm()->createView(), $templates, ['form_div_layout.html.twig']);

        $this->assertStringContainsString('<em><input type="text" id="user_email"', $traced);
        $this->assertSame($plain, $traced);

        $row = $this->getRendering('user', 'name')['blocks']['row']['matched'][0];
        $this->assertSame('form_div_layout.html.twig', $row['template']);
        $this->assertSame(realpath(\dirname(__DIR__, 2).'/Resources/views/Form/form_div_layout.html.twig'), realpath($row['path']));
        $this->assertStringStartsWith('{%- block form_row -%}', explode("\n", file_get_contents($row['path']))[$row['line'] - 1]);
    }

    public function testResetForgetsTheRecordedThemes()
    {
        $engine = $this->createEngine(['custom.html.twig' => '{% block form_widget %}(custom){% endblock %}']);
        $renderer = new FormRenderer($engine);

        $view = $this->createUserForm()->createView();
        $renderer->setTheme($view, 'custom.html.twig');
        $engine->reset();

        $this->assertSame('<a@b.c>', $renderer->searchAndRenderBlock($view['email'], 'widget'));
        $this->assertSame('layout.html.twig', $this->getRendering('user', 'email')['blocks']['widget']['matched'][0]['template']);
    }

    private function createUserForm(string $name = 'user')
    {
        return $this->factory->createNamedBuilder($name, FormType::class, ['email' => 'a@b.c'])
            ->add('email', EmailType::class)
            ->add('name', TextType::class)
            ->getForm();
    }

    private function render(string $template, FormView $view, array $templates = [], array $defaultThemes = ['layout.html.twig'], bool $traced = true, array $environmentOptions = []): string
    {
        $this->createEngine(['page.html.twig' => $template] + $templates, $defaultThemes, $traced, $environmentOptions);

        return $this->environment->render('page.html.twig', ['form' => $view]);
    }

    private function createEngine(array $templates, array $defaultThemes = ['layout.html.twig'], bool $traced = true, array $environmentOptions = [], ?FormDataCollectorInterface $collector = null): FormRendererEngineInterface
    {
        $this->environment = new Environment(new ChainLoader([
            new ArrayLoader($templates + ['layout.html.twig' => self::LAYOUT]),
            new FilesystemLoader(\dirname(__DIR__, 2).'/Resources/views/Form'),
        ]), $environmentOptions);
        $this->environment->addExtension(new TranslationExtension(new StubTranslator()));
        $this->environment->addExtension(new FormExtension());
        $this->environment->addExtension(new StringLoaderExtension());
        $this->environment->addFunction(new TwigFunction('form_notice', null, ['node_class' => SearchAndRenderBlockNode::class, 'is_safe' => ['html']]));

        $engine = new TwigRendererEngine($defaultThemes, $this->environment);

        if ($traced) {
            $engine = new TraceableTwigRendererEngine($engine, $this->environment, $defaultThemes, $collector ?? $this->collector);
        }

        $this->registerTwigRuntimeLoader($this->environment, new FormRenderer($engine));

        return $engine;
    }

    /**
     * @return array{FormRenderer, Environment}
     */
    private function createRendererWithStubEngine(array $templates): array
    {
        $environment = new Environment(new ArrayLoader($templates));

        return [new FormRenderer(new TraceableTwigRendererEngine(new StubRendererEngine(), $environment, [], $this->collector)), $environment];
    }

    private function getRendering(string $form, ?string $child = null): ?array
    {
        $data = $this->collector->getData()['forms'][$form];

        if (null !== $child) {
            $data = $data['children'][$child];
        }

        return $data['rendering'] ?? null;
    }
}

class StubRendererEngine implements FormRendererEngineInterface, ResetInterface
{
    public int $resets = 0;

    public function setTheme(FormView $view, mixed $themes, bool $useDefaultThemes = true): void
    {
    }

    public function getResourceForBlockName(FormView $view, string $blockName): mixed
    {
        return true;
    }

    public function getResourceForBlockNameHierarchy(FormView $view, array $blockNameHierarchy, int $hierarchyLevel): mixed
    {
        return true;
    }

    public function getResourceHierarchyLevel(FormView $view, array $blockNameHierarchy, int $hierarchyLevel): int|false
    {
        return $hierarchyLevel;
    }

    public function renderBlock(FormView $view, mixed $resource, string $blockName, array $variables = []): string
    {
        return '';
    }

    public function reset(): void
    {
        ++$this->resets;
    }
}
