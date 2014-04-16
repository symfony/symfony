<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Translation;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Translation\Extractor\ExtractorInterface;
use Symfony\Component\Translation\MessageCatalogue;

/**
 * TwigExtractor extracts translation messages from a twig template.
 *
 * @author Michel Salib <michelsalib@hotmail.com>
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TwigExtractor implements ExtractorInterface
{
    /**
     * Default domain for found messages.
     *
     * @var string
     */
    private $defaultDomain = 'messages';

    /**
     * Prefix for found message.
     *
     * @var string
     */
    private $prefix = '';

    /**
     * The twig environment.
     *
     * @var \Twig_Environment
     */
    private $twig;

    public function __construct(\Twig_Environment $twig)
    {
        $this->twig = $twig;
    }

    /**
     * {@inheritdoc}
     */
    public function extract($directory, MessageCatalogue $catalogue)
    {
        // load any existing translation files
        $finder = new Finder();
        $files = $finder->files()->name('*.twig')->sortByName()->in($directory);
        foreach ($files as $file) {
            $this->extractTemplate(file_get_contents($file->getPathname()), $catalogue);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
    }

    protected function extractTemplate($template, MessageCatalogue $catalogue)
    {
        $visitor = $this->twig->getExtension('translator')->getTranslationNodeVisitor();
        $visitor->enable();

        $this->twig->parse($this->twig->tokenize($template));

        foreach ($visitor->getMessages() as $message) {
            $catalogue->set(trim($message[0]), $this->prefix.trim($message[0]), $message[1] ? $message[1] : $this->defaultDomain);
        }

        $visitor->disable();
    }
}
