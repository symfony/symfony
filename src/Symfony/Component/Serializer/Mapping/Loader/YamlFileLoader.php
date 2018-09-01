<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Mapping\Loader;

use Symfony\Component\Serializer\Exception\MappingException;
use Symfony\Component\Serializer\Mapping\AttributeMetadata;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorMapping;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Yaml;

/**
 * YAML File Loader.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class YamlFileLoader extends FileLoader
{
    private $yamlParser;

    /**
     * An array of YAML class descriptions.
     *
     * @var array
     */
    private $classes;

    /**
     * {@inheritdoc}
     */
    public function loadClassMetadata(ClassMetadataInterface $classMetadata)
    {
        if (null === $this->classes) {
            $this->classes = $this->getClassesFromYaml();
        }

        if (!$this->classes) {
            return false;
        }

        if (!isset($this->classes[$classMetadata->getName()])) {
            return false;
        }

        $yaml = $this->classes[$classMetadata->getName()];
        if (isset($yaml['attributes']) && \is_array($yaml['attributes'])) {
            $attributesMetadata = $classMetadata->getAttributesMetadata();

            foreach ($yaml['attributes'] as $attribute => $data) {
                if (isset($attributesMetadata[$attribute])) {
                    $attributeMetadata = $attributesMetadata[$attribute];
                } else {
                    $attributeMetadata = new AttributeMetadata($attribute);
                    $classMetadata->addAttributeMetadata($attributeMetadata);
                }

                if (isset($data['groups'])) {
                    if (!\is_array($data['groups'])) {
                        throw new MappingException(sprintf('The "groups" key must be an array of strings in "%s" for the attribute "%s" of the class "%s".', $this->file, $attribute, $classMetadata->getName()));
                    }

                    foreach ($data['groups'] as $group) {
                        if (!\is_string($group)) {
                            throw new MappingException(sprintf('Group names must be strings in "%s" for the attribute "%s" of the class "%s".', $this->file, $attribute, $classMetadata->getName()));
                        }

                        $attributeMetadata->addGroup($group);
                    }
                }

                if (isset($data['methods'])) {
                    if (!\is_array($data['methods'])) {
                        throw new MappingException(sprintf('The "methods" key must be an array in "%s" for the attribute "%s" of the class "%s".', $this->file, $attribute, $classMetadata->getName()));
                    }

                    foreach ($data['methods'] as $methods) {
                        if (isset($methods['accessor'])) {
                            if (!\is_string($methods['accessor'])) {
                                throw new MappingException(sprintf('The value of "methods.accessor" must be a in "%s" for the attribute "%s" of the class "%s".', $this->file, $attribute, $classMetadata->getName()));
                            }
                            $attributeMetadata->setMethodsAccessor($methods['accessor']);
                        }

                        if (isset($methods['mutator'])) {
                            if (!\is_string($methods['mutator'])) {
                                throw new MappingException(sprintf('The value of "methods.mutator" must be a in "%s" for the attribute "%s" of the class "%s".', $this->file, $attribute, $classMetadata->getName()));
                            }
                            $attributeMetadata->setMethodsAccessor($methods['mutator']);
                        }
                    }
                }

                if (isset($data['exclude'])) {
                    if (!\is_bool($data['exclude'])) {
                        throw new MappingException(sprintf('The "exclude" value must be a boolean in "%s" for the attribute "%s" of the class "%s".', $this->file, $attribute, $classMetadata->getName()));
                    }

                    $attributeMetadata->setExclude($data['exclude']);
                }

                if (isset($data['expose'])) {
                    if (!\is_bool($data['expose'])) {
                        throw new MappingException(sprintf('The "expose" value must be a boolean in "%s" for the attribute "%s" of the class "%s".', $this->file, $attribute, $classMetadata->getName()));
                    }

                    $attributeMetadata->setExpose($data['expose']);
                }

                if (isset($data['max_depth'])) {
                    if (!\is_int($data['max_depth'])) {
                        throw new MappingException(sprintf('The "max_depth" value must be an integer in "%s" for the attribute "%s" of the class "%s".', $this->file, $attribute, $classMetadata->getName()));
                    }

                    $attributeMetadata->setMaxDepth($data['max_depth']);
                }

                if (isset($data['read_only'])) {
                    if (!\is_bool($data['read_only'])) {
                        throw new MappingException(sprintf('The "read_only" value must be a boolean in "%s" for the attribute "%s" of the class "%s".', $this->file, $attribute, $classMetadata->getName()));
                    }

                    $attributeMetadata->setReadOnly($data['read_only']);
                }

                if (isset($data['serialized_name'])) {
                    if (!\is_string($data['serialized_name'])) {
                        throw new MappingException(sprintf('The "serialized_name" value must be a string in "%s" for the attribute "%s" of the class "%s".', $this->file, $attribute, $classMetadata->getName()));
                    }

                    $attributeMetadata->setReadOnly($data['serialized_name']);
                }

                if (isset($data['type'])) {
                    if (!\is_string($data['type'])) {
                        throw new MappingException(sprintf('The "type" value must be a string in "%s" for the attribute "%s" of the class "%s".', $this->file, $attribute, $classMetadata->getName()));
                    }

                    $attributeMetadata->setReadOnly($data['type']);
                }
            }
        }

        if (isset($yaml['exclusion_policy'])) {
            if (!\is_string($yaml['exclusion_policy'])) {
                throw new MappingException(sprintf('The "exclusion_policy" value must be a string in "%s" for the class "%s".', $this->file, $classMetadata->getName()));
            }

            $classMetadata->setExclusionPolicy($yaml['exclusion_policy']);
        }
        if (isset($yaml['read_only'])) {
            if (!\is_bool($yaml['read_only'])) {
                throw new MappingException(sprintf('The "read_only" value must be a boolean in "%s" for the class "%s".', $this->file, $classMetadata->getName()));
            }

            $classMetadata->setReadOnly($yaml['read_only']);
        }

        if (isset($yaml['discriminator_map'])) {
            if (!isset($yaml['discriminator_map']['type_property'])) {
                throw new MappingException(sprintf('The "type_property" key must be set for the discriminator map of the class "%s" in "%s".', $classMetadata->getName(), $this->file));
            }

            if (!isset($yaml['discriminator_map']['mapping'])) {
                throw new MappingException(sprintf('The "mapping" key must be set for the discriminator map of the class "%s" in "%s".', $classMetadata->getName(), $this->file));
            }

            $classMetadata->setClassDiscriminatorMapping(new ClassDiscriminatorMapping(
                $yaml['discriminator_map']['type_property'],
                $yaml['discriminator_map']['mapping']
            ));
        }

        return true;
    }

    /**
     * Return the names of the classes mapped in this file.
     *
     * @return string[] The classes names
     */
    public function getMappedClasses()
    {
        if (null === $this->classes) {
            $this->classes = $this->getClassesFromYaml();
        }

        return array_keys($this->classes);
    }

    private function getClassesFromYaml()
    {
        if (!stream_is_local($this->file)) {
            throw new MappingException(sprintf('This is not a local file "%s".', $this->file));
        }

        if (null === $this->yamlParser) {
            $this->yamlParser = new Parser();
        }

        $classes = $this->yamlParser->parseFile($this->file, Yaml::PARSE_CONSTANT);

        if (empty($classes)) {
            return array();
        }

        if (!\is_array($classes)) {
            throw new MappingException(sprintf('The file "%s" must contain a YAML array.', $this->file));
        }

        return $classes;
    }
}
