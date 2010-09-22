<?php

namespace Symfony\Component\Form\Renderer;

use Symfony\Component\I18N\TranslatorInterface;
use Symfony\Component\Form\FieldInterface;
use Symfony\Component\Form\HtmlGeneratorInterface;
use Symfony\Component\Form\Configurable;

/**
 * BaseRenderer is the base class for all renderers.
 */
abstract class Renderer extends Configurable implements RendererInterface
{
    /**
     * The generator used for rendering the HTML
     * @var HtmlGeneratorInterface
     */
    protected $generator;

    /**
     * Gets the stylesheet paths associated with the renderer.
     *
     * The array keys are files and values are the media names (separated by a ,):
     *
     *   array('/path/to/file.css' => 'all', '/another/file.css' => 'screen,print')
     *
     * @return array An array of stylesheet paths
     */
    public function getStylesheets()
    {
        return array();
    }

    /**
     * Gets the JavaScript paths associated with the renderer.
     *
     * @return array An array of JavaScript paths
     */
    public function getJavaScripts()
    {
        return array();
    }

    /**
     * {@inheritDoc}
     */
    public function renderErrors(FieldInterface $field)
    {
        $html = '';

        if ($field->hasErrors()) {
            $html .= "<ul>\n";

            foreach ($field->getErrors() as $error) {
                $html .= "<li>" . $error . "</li>\n";
            }

            $html .= "</ul>\n";
        }

        return $html;
    }

    /**
     * {@inheritDoc}
     */
    public function setTranslator(TranslatorInterface $translator)
    {
        // TODO
    }

    /**
     * {@inheritDoc}
     */
    public function setLocale($locale)
    {
        // TODO
    }

    /**
     * {@inheritDoc}
     */
    public function setGenerator(HtmlGeneratorInterface $generator)
    {
        $this->generator = $generator;
    }
}
