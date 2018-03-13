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
use Symfony\Component\Finder\SplFileInfo;
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
        $files = $this->extractFiles($resource);
        foreach ($files as $file) {
            try {
                $this->extractTemplate(file_get_contents($file->getPathname()), $catalogue);
            } catch (Error $e) {
                if ($file instanceof \SplFileInfo) {
                    $path = $file->getRealPath() ?: $file->getPathname();
                    $name = $file instanceof SplFileInfo ? $file->getRelativePathname() : $path;
                    if (method_exists($e, 'setSourceContext')) {
                        $e->setSourceContext(new Source('', $name, $path));
                    } else {
                        $e->setTemplateName($name);
                    }
                }

                throw $e;
            }
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
        $visitor = $this->twig->getExtension('Symfony\Bridge\Twig\Extension\TranslationExtension')->getTranslationNodeVisitor();
        $visitor->enable();

        $this->twig->parse($this->twig->tokenize(new Source($template, '')));

        foreach ($visitor->getMessages() as $message) {
            $catalogue->set(trim($message[0]), $this->prefix.trim($message[0]), $message[1] ?: $this->defaultDomain);
        }

        $visitor->disable();
    }

    /**
     * @param string $file
     *
     * @return bool
     */
    protected function canBeExtracted($file)
    {
        return $this->isFile($file) && 'twig' === pathinfo($file, PATHINFO_EXTENSION);
    }

    /**
     * @param string|array $directory
     *
     * @return array
     */
    protected function extractFromDirectory($directory)
    {
        $finder = new Finder();

        return $finder->files()->name('*.twig')->in($directory);
    }
}
