<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Mapping\Loader;

use Symfony\Component\PropertyAccess\Exception\MappingException;
use Symfony\Component\PropertyAccess\Mapping\PropertyMetadata;
use Symfony\Component\PropertyAccess\Mapping\ClassMetadata;
use Symfony\Component\Yaml\Parser;

/**
 * YAML File Loader.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Luis Ramón López <lrlopez@gmail.com>
 */
class YamlFileLoader extends FileLoader
{
    private $yamlParser;

    /**
     * An array of YAML class descriptions.
     *
     * @var array
     */
    private $classes = null;

    /**
     * {@inheritdoc}
     */
    public function loadClassMetadata(ClassMetadata $classMetadata)
    {
        if (null === $this->classes) {
            if (!stream_is_local($this->file)) {
                throw new MappingException(sprintf('This is not a local file "%s".', $this->file));
            }

            if (null === $this->yamlParser) {
                $this->yamlParser = new Parser();
            }

            $classes = $this->yamlParser->parse(file_get_contents($this->file));

            if (empty($classes)) {
                return false;
            }

            // not an array
            if (!is_array($classes)) {
                throw new MappingException(sprintf('The file "%s" must contain a YAML array.', $this->file));
            }

            $this->classes = $classes;
        }

        if (isset($this->classes[$classMetadata->getName()])) {
            $yaml = $this->classes[$classMetadata->getName()];

            if (isset($yaml['properties']) && is_array($yaml['properties'])) {
                $attributesMetadata = $classMetadata->getPropertyMetadataCollection();

                foreach ($yaml['properties'] as $attribute => $data) {
                    if (isset($attributesMetadata[$attribute])) {
                        $attributeMetadata = $attributesMetadata[$attribute];
                    } else {
                        $attributeMetadata = new PropertyMetadata($attribute);
                        $classMetadata->addPropertyMetadata($attributeMetadata);
                    }

                    if (isset($data['getter'])) {
                        if (!is_string($data['getter'])) {
                            throw new MappingException('The "getter" value must be a string in "%s" for the attribute "%s" of the class "%s".', $this->file, $attribute, $classMetadata->getName());
                        }

                        $attributeMetadata->setGetter($data['getter']);
                    }

                    if (isset($data['setter'])) {
                        if (!is_string($data['setter'])) {
                            throw new MappingException('The "setter" value must be a string in "%s" for the attribute "%s" of the class "%s".', $this->file, $attribute, $classMetadata->getName());
                        }

                        $attributeMetadata->setSetter($data['setter']);
                    }

                    if (isset($data['adder'])) {
                        if (!is_string($data['adder'])) {
                            throw new MappingException('The "adder" value must be a string in "%s" for the attribute "%s" of the class "%s".', $this->file, $attribute, $classMetadata->getName());
                        }

                        $attributeMetadata->setAdder($data['adder']);
                    }

                    if (isset($data['remover'])) {
                        if (!is_string($data['remover'])) {
                            throw new MappingException('The "remover" value must be a string in "%s" for the attribute "%s" of the class "%s".', $this->file, $attribute, $classMetadata->getName());
                        }

                        $attributeMetadata->setRemover($data['remover']);
                    }
                }
            }

            return true;
        }

        return false;
    }
}
