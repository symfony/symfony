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

use Symfony\Component\Translation\Extractor\ExtractorInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\XmlFileLoader;
use Symfony\Component\Validator\Mapping\Loader\YamlFileLoader;

/**
 * @author Webnet team <comptewebnet@webnet.fr>
 */
abstract class AbstractFileValidationExtractor implements ExtractorInterface
{
    use ValidationExtractorTrait;

    /**
     * Files to extract validation metadata from.
     *
     * @var string[]
     */
    private $files = [];

    /**
     * Constructor
     *
     * @param string $defaultDomain      Default translation messages domain
     */
    public function __construct($defaultDomain = 'validators')
    {
        $this->defaultDomain = $defaultDomain;
    }

    /**
     * @inheritdoc
     */
    public function extract($resource, MessageCatalogue $catalogue)
    {
        foreach ($this->files as $file) {
            $loader = $this->createLoader($file);
            foreach ($loader->getMappedClasses() as $class) {
                $metadata = new ClassMetadata($class);
                $loader->loadClassMetadata($metadata);

                $this->extractMessagesFromMetadata($catalogue, $metadata);
            }
        }
    }

    /**
     * Set files to extract validation metadata from.
     *
     * @param string[] $files
     */
    public function setFiles(array $files)
    {
        $this->files = $files;
    }

    /**
     * Create a new metadata loader for given file.
     *
     * @param string $file
     *
     * @return XmlFileLoader|YamlFileLoader
     */
    protected abstract function createLoader(string $file);
}
