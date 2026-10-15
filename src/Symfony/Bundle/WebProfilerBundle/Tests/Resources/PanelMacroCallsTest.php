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
use Symfony\Bundle\WebProfilerBundle\Twig\WebProfilerExtension;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\DataCollector\DataCollectorExtension;
use Symfony\Component\Form\Extension\DataCollector\FormDataCollector;
use Symfony\Component\Form\Extension\DataCollector\FormDataExtractor;
use Symfony\Component\Form\Extension\DataCollector\Proxy\ResolvedTypeFactoryDataCollectorProxy;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\ResolvedFormTypeFactory;
use Symfony\Component\Translation\DataCollector\TranslationDataCollector;
use Symfony\Component\Translation\DataCollectorTranslator;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class PanelMacroCallsTest extends TestCase
{
    public function testFormPanelRendersTheDetailsOfChildForms()
    {
        if (!class_exists(FormDataCollector::class)) {
            $this->markTestSkipped('The Form component is not installed.');
        }

        $collector = new FormDataCollector(new FormDataExtractor());
        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new DataCollectorExtension($collector))
            ->setResolvedTypeFactory(new ResolvedTypeFactoryDataCollectorProxy(new ResolvedFormTypeFactory(), $collector))
            ->getFormFactory();

        $factory->createNamedBuilder('user')
            ->add('email', TextType::class)
            ->getForm()
            ->createView();

        $panel = $this->renderPanel('@WebProfiler/Collector/form.html.twig', $collector);

        $this->assertStringContainsString('id="user_email-details"', $panel);
    }

    public function testTranslationPanelRendersTheDefinedFallbackAndMissingMessages()
    {
        if (!class_exists(TranslationDataCollector::class)) {
            $this->markTestSkipped('The Translation component is not installed.');
        }

        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['defined_message' => 'Defined'], 'en');
        $translator->addResource('array', ['fallback_message' => 'Fallback'], 'fr');
        $translator->setFallbackLocales(['fr']);

        $translator = new DataCollectorTranslator($translator);
        $translator->trans('defined_message');
        $translator->trans('fallback_message');
        $translator->trans('missing_message');

        $collector = new TranslationDataCollector($translator);
        $collector->lateCollect();

        $panel = $this->renderPanel('@WebProfiler/Collector/translation.html.twig', $collector);

        $this->assertStringContainsString('defined_message', $panel);
        $this->assertStringContainsString('fallback_message', $panel);
        $this->assertStringContainsString('missing_message', $panel);
    }

    private function renderPanel(string $template, object $collector): string
    {
        $collector = unserialize(serialize($collector));

        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2).'/Resources/views', 'WebProfiler');

        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addExtension(new WebProfilerExtension());

        $deprecations = [];
        set_error_handler(static function (int $type, string $message) use (&$deprecations) {
            $deprecations[] = $message;

            return true;
        }, \E_USER_DEPRECATED);

        try {
            $panel = $twig->load($template)->renderBlock('panel', ['collector' => $collector]);
        } finally {
            restore_error_handler();
        }

        // Twig 3.29 deprecates omitting a macro argument that has no default value, and Twig 4 throws
        $this->assertSame([], array_values(preg_grep('/ of macro "/', $deprecations)));

        return $panel;
    }
}
