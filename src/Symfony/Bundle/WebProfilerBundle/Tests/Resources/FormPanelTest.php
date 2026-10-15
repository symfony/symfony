<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Tests\Resources;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\WebProfilerBundle\Profiler\CodeExtension;
use Symfony\Bundle\WebProfilerBundle\Twig\WebProfilerExtension;
use Symfony\Component\ErrorHandler\ErrorRenderer\FileLinkFormatter;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\DataCollector\DataCollectorExtension;
use Symfony\Component\Form\Extension\DataCollector\FormDataCollector;
use Symfony\Component\Form\Extension\DataCollector\FormDataExtractor;
use Symfony\Component\Form\Extension\DataCollector\Proxy\ResolvedTypeFactoryDataCollectorProxy;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\ResolvedFormTypeFactory;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class FormPanelTest extends TestCase
{
    protected function setUp(): void
    {
        if (!method_exists(FormDataCollector::class, 'collectRenderingTrace')) {
            $this->markTestSkipped('The Form component does not collect rendering traces.');
        }
    }

    public function testThemeTabShowsHowAFieldWasRendered()
    {
        $panel = $this->renderPanel(static function (FormDataCollector $collector, array $views) {
            $collector->collectRenderingTrace($views['email'], ['blocks' => [
                'widget' => [
                    'hierarchy' => ['form_widget', 'text_widget', 'email_widget', '_user_email_widget'],
                    'matched' => [['block' => 'email_widget', 'template' => 'form/<custom>.html.twig', 'path' => '/app/templates/form/custom.html.twig', 'line' => 3]],
                ],
            ]]);
        });

        $email = $this->getDetails($panel, 'user_email');

        $this->assertStringContainsString('<th scope="col">Candidate blocks</th>', $email);
        $this->assertMatchesRegularExpression('{<th scope="row" rowspan="1">widget</th>\s*<td rowspan="1">\s*<code class="skipped">form_widget</code><br>\s*<code class="skipped">text_widget</code><br>\s*<code class="matched">email_widget</code><br>\s*<code class="skipped">_user_email_widget</code>}', $email);
        $this->assertStringContainsString('<a href="editor://open?file=/app/templates/form/custom.html.twig&amp;line=3" title="templates/form/custom.html.twig">form/&lt;custom&gt;.html.twig:3</a>', $email);
        $this->assertMatchesRegularExpression('{<button type="button" class="icon-copy hidden" aria-label="Copy file path" data-clipboard-text="templates/form/custom.html.twig:3">\s*<span class="icon-copy-default"><svg[^>]*data-icon-name="icon-tabler-copy"}', $email);
        $this->assertMatchesRegularExpression('{<th scope="row">help</th>\s*<td colspan="2" class="text-muted">Not rendered</td>}', $email);
        $this->assertStringNotContainsString('<th scope="row">rest</th>', $email);
        $this->assertStringNotContainsString('<th scope="row">form_start</th>', $email);
        $this->assertStringNotContainsString('Built-in parts', $email);
        $this->assertStringNotContainsString('Custom parts', $email);
    }

    public function testADelegationChainListsWhereEachMatchedBlockIsDefined()
    {
        $panel = $this->renderPanel(static function (FormDataCollector $collector, array $views) {
            $collector->collectRenderingTrace($views['email'], ['blocks' => [
                'widget' => [
                    'hierarchy' => ['form_widget', 'text_widget', 'email_widget', '_user_email_widget'],
                    'matched' => [
                        ['block' => 'form_widget', 'template' => 'form_div_layout.html.twig', 'path' => null, 'line' => 9],
                        ['block' => 'email_widget', 'template' => 'form/custom.html.twig', 'path' => null, 'line' => 3],
                    ],
                ],
            ]]);
        });

        $email = $this->getDetails($panel, 'user_email');

        $this->assertMatchesRegularExpression('{<th scope="row" rowspan="2">widget</th>\s*<td rowspan="2">\s*<code class="matched">form_widget</code>}', $email);
        $this->assertMatchesRegularExpression('{<td>\s*<code>form_widget</code>:\s*<span>form_div_layout.html.twig:9</span>\s*</td>\s*</tr>\s*<tr>\s*<td>\s*<code>email_widget</code>:\s*<span>form/custom.html.twig:3</span>\s*</td>}', $email);
    }

    public function testThePathOfATemplateOutsideTheProjectIsKeptAbsolute()
    {
        $panel = $this->renderPanel(static function (FormDataCollector $collector, array $views) {
            $collector->collectRenderingTrace($views['email'], ['blocks' => [
                'widget' => ['hierarchy' => ['form_widget'], 'matched' => [['block' => 'form_widget', 'template' => 'x.html.twig', 'path' => '/opt/themes/x.html.twig', 'line' => null]]],
            ]]);
        });

        $email = $this->getDetails($panel, 'user_email');

        $this->assertStringContainsString('title="/opt/themes/x.html.twig">x.html.twig</a>', $email);
        $this->assertStringContainsString('data-clipboard-text="/opt/themes/x.html.twig"', $email);
    }

    public function testDefinedInDegradesWhenTheTemplateOrLineIsUnknown()
    {
        $panel = $this->renderPanel(static function (FormDataCollector $collector, array $views) {
            $collector->collectRenderingTrace($views['email'], ['blocks' => [
                'widget' => ['hierarchy' => ['form_widget'], 'matched' => [['block' => 'form_widget', 'template' => null, 'path' => null, 'line' => null]]],
                'label' => ['hierarchy' => ['form_label'], 'matched' => [['block' => 'form_label', 'template' => '__string_template__abc', 'path' => null, 'line' => null]]],
            ]]);
        });

        $email = $this->getDetails($panel, 'user_email');

        $this->assertMatchesRegularExpression('{<code class="matched">form_widget</code>\s*</td>\s*<td>\s*<em>Unknown</em>}', $email);
        $this->assertStringContainsString('<span>__string_template__abc</span>', $email);
        $this->assertStringNotContainsString('icon-copy', $email);
    }

    public function testCustomPartsAreListedInTheirOwnGroup()
    {
        $panel = $this->renderPanel(static function (FormDataCollector $collector, array $views) {
            $collector->collectRenderingTrace($views['email'], ['blocks' => [
                'notice' => ['hierarchy' => ['form_notice', 'text_notice', 'email_notice', '_user_email_notice'], 'matched' => [['block' => 'form_notice', 'template' => 'form/fields.html.twig', 'path' => null, 'line' => 10]]],
                'widget' => ['hierarchy' => ['form_widget'], 'matched' => [['block' => 'form_widget', 'template' => 'form_div_layout.html.twig', 'path' => null, 'line' => 3]]],
            ]]);
        });

        $email = $this->getDetails($panel, 'user_email');

        $this->assertMatchesRegularExpression('{<tbody>\s*<tr>\s*<th colspan="3" class="form-theme-group font-normal">Built-in parts</th>\s*</tr>\s*<tr>\s*<th scope="row">row</th>}', $email);
        $this->assertMatchesRegularExpression('{<tbody>\s*<tr>\s*<th colspan="3" class="form-theme-group font-normal">Custom parts</th>\s*</tr>\s*<tr>\s*<th scope="row" rowspan="1">notice</th>\s*<td rowspan="1">\s*<code class="matched">form_notice</code>}', $email);
        $this->assertStringContainsString('<span>form/fields.html.twig:10</span>', $email);
        $this->assertSame(1, substr_count($email, '>notice</th>'));
        $this->assertLessThan(strpos($email, 'Custom parts'), strpos($email, '>widget</th>'));
    }

    public function testABuiltInPartRenderedOutsideItsUsualPlaceIsListedWithTheBuiltInOnes()
    {
        $panel = $this->renderPanel(static function (FormDataCollector $collector, array $views) {
            $collector->collectRenderingTrace($views['email'], ['blocks' => [
                'rest' => ['hierarchy' => ['form_rest'], 'matched' => [['block' => 'form_rest', 'template' => 'form_div_layout.html.twig', 'path' => null, 'line' => 440]]],
            ]]);
        });

        $email = $this->getDetails($panel, 'user_email');

        $this->assertMatchesRegularExpression('{<th scope="row" rowspan="1">rest</th>}', $email);
        $this->assertStringNotContainsString('Custom parts', $email);
    }

    public function testRootFormListsItsOwnParts()
    {
        $panel = $this->renderPanel(static function (FormDataCollector $collector, array $views) {
            $collector->collectRenderingTrace($views['user'], ['blocks' => ['form_start' => ['hierarchy' => ['form_start'], 'matched' => [['block' => 'form_start', 'template' => 'form_div_layout.html.twig', 'path' => null, 'line' => 388]]]]]);
        });

        $user = $this->getDetails($panel, 'user');

        $this->assertMatchesRegularExpression('{<th scope="row">rest</th>\s*<td colspan="2" class="text-muted">Not rendered</td>}', $user);
        $this->assertMatchesRegularExpression('{<th scope="row" rowspan="1">form_start</th>\s*<td rowspan="1">\s*<code class="matched">form_start</code>}', $user);
        $this->assertStringContainsString('<th scope="row">form_end</th>', $user);
        $this->assertStringContainsString('<th scope="row">form</th>', $user);
    }

    public function testFieldsLeftOutOfARenderedFormAreMarked()
    {
        $panel = $this->renderPanel(static function (FormDataCollector $collector, array $views) {
            $collector->collectRenderingTrace($views['user'], ['blocks' => ['form_start' => self::createBlock('form_start')]]);
            $collector->collectRenderingTrace($views['email'], ['blocks' => ['row' => self::createBlock('form_row')]]);
            $collector->collectRenderingTrace($views['name'], ['blocks' => ['label' => self::createBlock('form_label')]]);
        });

        $this->assertMatchesRegularExpression('{<span class="form-not-rendered">\s*name\s*</span>}', $panel);
        $this->assertMatchesRegularExpression('{<span>\s*email\s*</span>}', $panel);
        $this->assertMatchesRegularExpression('{<span>\s*user\s*</span>}', $panel);
    }

    public function testARootFormWithoutChildrenIsNeverMarked()
    {
        $panel = $this->renderPanel(static function (FormDataCollector $collector, array $views) {
            $collector->collectRenderingTrace($views['user'], ['blocks' => ['form' => self::createBlock('form')]]);
        }, static fn (FormFactoryInterface $factory) => $factory->createNamed('user', TextType::class));

        $this->assertStringNotContainsString('form-not-rendered', $panel);
        $this->assertStringNotContainsString('(not rendered)', $panel);
    }

    public function testCollectionPrototypeIsShownAsASeparateEntry()
    {
        $panel = $this->renderPanel(static function () {});

        $this->assertStringContainsString('<span class="badge form-prototype-badge">prototype</span>', $panel);
        $this->assertStringContainsString('data-tab-target-id="user_tags___name__-details"', $panel);
        $this->assertStringContainsString('<h2>__name__ (prototype)</h2>', $panel);
    }

    public function testThemeTabIsDisabledWithoutRenderingTrace()
    {
        $panel = $this->renderPanel(static function () {});

        $this->assertMatchesRegularExpression('{<div class="tab disabled">\s*<h3 class="tab-title">Theme</h3>}', $panel);
    }

    private function getDetails(string $panel, string $id): string
    {
        $this->assertSame(1, preg_match('{<div class="tree-details[^"]*" id="'.preg_quote($id).'-details">}', $panel, $matches, \PREG_OFFSET_CAPTURE), \sprintf('No details for "%s".', $id));
        $start = $matches[0][1];
        $end = strpos($panel, '<div class="tree-details', $start + 1);

        return false === $end ? substr($panel, $start) : substr($panel, $start, $end - $start);
    }

    private static function createBlock(string $name): array
    {
        return ['hierarchy' => [$name], 'matched' => [['block' => $name, 'template' => 'form_div_layout.html.twig', 'path' => null, 'line' => 1]]];
    }

    private function renderPanel(\Closure $trace, ?\Closure $createForm = null): string
    {
        $collector = new FormDataCollector(new FormDataExtractor());
        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new DataCollectorExtension($collector))
            ->setResolvedTypeFactory(new ResolvedTypeFactoryDataCollectorProxy(new ResolvedFormTypeFactory(), $collector))
            ->getFormFactory();

        $createForm ??= static fn (FormFactoryInterface $factory) => $factory->createNamedBuilder('user')
            ->add('email', EmailType::class)
            ->add('name', TextType::class)
            ->add('tags', CollectionType::class, ['entry_type' => TextType::class, 'allow_add' => true])
            ->getForm();
        $view = $createForm($factory)->createView();

        $trace($collector, ['user' => $view] + $view->children);
        $collector = unserialize(serialize($collector));

        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2).'/Resources/views', 'WebProfiler');

        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addExtension(new WebProfilerExtension());
        $twig->addExtension(new CodeExtension(new FileLinkFormatter('editor://open?file=%f&line=%l'), '/app', 'UTF-8'));

        return $twig
            ->load('@WebProfiler/Collector/form.html.twig')
            ->renderBlock('panel', ['collector' => $collector])
        ;
    }
}
