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
use Symfony\Component\Translation\Extractor\AbstractFileExtractor;
use Symfony\Component\Translation\Extractor\ExtractorInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Twig\Environment;
use Twig\Error\Error;
use Twig\Source;

/**
 * TwigExtractor extracts translation messages from a twig template.
 *
 * @author Michel Salib <michelsalib@hotmail.com>
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TwigExtractor extends AbstractFileExtractor implements ExtractorInterface
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

    private $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    /**
     * {@inheritdoc}
     */
    public function extract($resource, MessageCatalogue $catalogue)
    {
        foreach ($this->extractFiles($resource) as $file) {
            try {
                $this->extractTemplate(file_get_contents($file->getPathname()), $catalogue);
            } catch (Error $e) {
                // ignore errors, these should be fixed by using the linter
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setPrefix(string $prefix)
    {
        $this->prefix = $prefix;
    }

    protected function extractTemplate(string $template, MessageCatalogue $catalogue)
    {
        $visitor = $this->twig->getExtension('Symfony\Bridge\Twig\Extension\TranslationExtension')->getTranslationNodeVisitor();
        $visitor->enable();

        $this->twig->parse($this->twig->tokenize(new Source($template, '')));

        foreach ($visitor->getMessages() as $extractedMessage) {
            $message = trim($extractedMessage[0]);
            $domain = $extractedMessage[1] ?: $this->defaultDomain;

            $catalogue->set($message, $this->prefix.trim($extractedMessage[0]), $domain);

            if (!empty($extractedMessage[2])) {
                $metadata = $catalogue->getMetadata($message, $domain);

                $metadata['notes'][] = [
                    'category' => 'symfony-extractor-variables',
                    'content' => 'Available variables: '.implode(', ', $extractedMessage[2]),
                ];

                $catalogue->setMetadata($message, $metadata, $domain);
            }
        }

        $visitor->disable();
    }

    /**
     * @return bool
     */
    protected function canBeExtracted(string $file)
    {
        return $this->isFile($file) && 'twig' === pathinfo($file, \PATHINFO_EXTENSION);
    }

    /**
     * {@inheritdoc}
     */
    protected function extractFromDirectory($directory)
    {
        $finder = new Finder();

        return $finder->files()->name('*.twig')->in($directory);
    }
}
