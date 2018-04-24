<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Translation\Extractor;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Symfony\Component\Translation\Extractor\AbstractFileExtractor;
use Symfony\Component\Translation\Extractor\ExtractorInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Validator\Mapping\Loader\StaticMethodLoader;

/**
 * Extractor of validation messages from php source files. In particular these are:
 * 1. Classes with annotated validation constraints (if annotations are enabled).
 * 2. Classes with a special static method that loads validation metadata
 * (https://symfony.com/doc/current/components/validator/metadata.html).
 *
 * @author Webnet team <comptewebnet@webnet.fr>
 */
class PhpValidationExtractor extends AbstractFileExtractor implements ExtractorInterface
{
    use ValidationExtractorTrait;

    /**
     * Whether annotations should be enabled.
     *
     * @var bool
     */
    private $annotationsEnabled;

    /**
     * @var AnnotationLoader
     */
    private $annotationLoader;

    /**
     * @var array
     */
    private $staticMethod;

    /**
     * Constructor.
     *
     * @param bool   $annotationsEnabled Annotations are enabled by default when validation and translation are enabled
     * @param array  $staticMethod       Name of static methods which load validation metadata
     * @param string $defaultDomain      Default translation messages domain
     */
    public function __construct($annotationsEnabled = false, $staticMethod = array('loadValidatorMetadata'), $defaultDomain = 'validators')
    {
        $this->annotationsEnabled = $annotationsEnabled;
        if ($annotationsEnabled) {
            AnnotationRegistry::registerLoader('class_exists');
            $this->annotationLoader = new AnnotationLoader(new AnnotationReader());
        }

        $this->staticMethod = $staticMethod;
        $this->defaultDomain = $defaultDomain;
    }

    /**
     * {@inheritdoc}
     */
    public function extract($resource, MessageCatalogue $catalogue)
    {
        $files = $this->extractFiles($resource);

        foreach ($files as $file) {
            $classes = (new FQCNExtractor($file))->getDeclaredClasses();

            foreach ($classes as $className) {
                try {
                    $metadata = new ClassMetadata($className);

                    // load metadata from annotations
                    if ($this->annotationsEnabled) {
                        $this->annotationLoader->loadClassMetadata($metadata);
                    }

                    // load metadata from static methods
                    foreach ($this->staticMethod as $staticMethod) {
                        $loader = new StaticMethodLoader($staticMethod);
                        $loader->loadClassMetadata($metadata);
                    }

                    // fill catalogue with validation messages from metadata
                    $this->extractMessagesFromMetadata($catalogue, $metadata);
                } catch (\Exception $e) {
                }
            }
        }

        return $catalogue;
    }

    /**
     * {@inheritdoc}
     */
    protected function canBeExtracted($file)
    {
        return $this->isFile($file) && 'php' === pathinfo($file, PATHINFO_EXTENSION);
    }

    /**
     * {@inheritdoc}
     */
    protected function extractFromDirectory($directory)
    {
        $iterator = new \RegexIterator(
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            ),
            '/^.+\.php$/i',
            \RecursiveRegexIterator::GET_MATCH
        );

        $files = array();

        foreach ($iterator as $file) {
            $files[] = $file[0];
        }

        return $files;
    }
}
