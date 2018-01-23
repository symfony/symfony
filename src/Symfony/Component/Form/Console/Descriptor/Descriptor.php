<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Console\Descriptor;

use Symfony\Component\Console\Descriptor\DescriptorInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\OutputStyle;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Form\ResolvedFormTypeInterface;
use Symfony\Component\Form\Util\OptionsResolverWrapper;
use Symfony\Component\OptionsResolver\Debug\OptionsResolverIntrospector;
use Symfony\Component\OptionsResolver\Exception\NoConfigurationException;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 *
 * @internal
 */
abstract class Descriptor implements DescriptorInterface
{
    /** @var OutputStyle */
    protected $output;
    protected $type;
    protected $ownOptions = array();
    protected $overriddenOptions = array();
    protected $parentOptions = array();
    protected $extensionOptions = array();
    protected $requiredOptions = array();
    protected $parents = array();
    protected $extensions = array();

    /**
     * {@inheritdoc}
     */
    public function describe(OutputInterface $output, $object, array $options = array())
    {
        $this->output = $output instanceof OutputStyle ? $output : new SymfonyStyle(new ArrayInput(array()), $output);

        switch (true) {
            case null === $object:
                $this->describeDefaults($options);
                break;
            case $object instanceof ResolvedFormTypeInterface:
                $this->describeResolvedFormType($object, $options);
                break;
            case $object instanceof OptionsResolver:
                $this->describeOption($object, $options);
                break;
            default:
                throw new \InvalidArgumentException(sprintf('Object of type "%s" is not describable.', get_class($object)));
        }
    }

    abstract protected function describeDefaults(array $options);

    abstract protected function describeResolvedFormType(ResolvedFormTypeInterface $resolvedFormType, array $options = array());

    abstract protected function describeOption(OptionsResolver $optionsResolver, array $options);

    protected function collectOptions(ResolvedFormTypeInterface $type)
    {
        $this->parents = array();
        $this->extensions = array();

        if (null !== $type->getParent()) {
            $optionsResolver = clone $this->getParentOptionsResolver($type->getParent());
        } else {
            $optionsResolver = new OptionsResolver();
        }

        $type->getInnerType()->configureOptions($ownOptionsResolver = new OptionsResolverWrapper());
        $this->ownOptions = array_diff($ownOptionsResolver->getDefinedOptions(), $optionsResolver->getDefinedOptions());
        $overriddenOptions = array_intersect(array_merge($ownOptionsResolver->getDefinedOptions(), $ownOptionsResolver->getUndefinedOptions()), $optionsResolver->getDefinedOptions());

        $this->parentOptions = array();
        foreach ($this->parents as $class => $parentOptions) {
            $this->overriddenOptions[$class] = array_intersect($overriddenOptions, $parentOptions);
            $this->parentOptions[$class] = array_diff($parentOptions, $overriddenOptions);
        }

        $type->getInnerType()->configureOptions($optionsResolver);
        $this->collectTypeExtensionsOptions($type, $optionsResolver);
        $this->extensionOptions = array();
        foreach ($this->extensions as $class => $extensionOptions) {
            $this->overriddenOptions[$class] = array_intersect($overriddenOptions, $extensionOptions);
            $this->extensionOptions[$class] = array_diff($extensionOptions, $overriddenOptions);
        }

        $this->overriddenOptions = array_filter($this->overriddenOptions);
        $this->parentOptions = array_filter($this->parentOptions);
        $this->extensionOptions = array_filter($this->extensionOptions);
        $this->requiredOptions = $optionsResolver->getRequiredOptions();

        $this->parents = array_keys($this->parents);
        $this->extensions = array_keys($this->extensions);
    }

    protected function getOptionDefinition(OptionsResolver $optionsResolver, $option)
    {
        $definition = array('required' => $optionsResolver->isRequired($option));

        $introspector = new OptionsResolverIntrospector($optionsResolver);

        $map = array(
            'default' => 'getDefault',
            'lazy' => 'getLazyClosures',
            'allowedTypes' => 'getAllowedTypes',
            'allowedValues' => 'getAllowedValues',
            'normalizer' => 'getNormalizer',
        );

        foreach ($map as $key => $method) {
            try {
                $definition[$key] = $introspector->{$method}($option);
            } catch (NoConfigurationException $e) {
                // noop
            }
        }

        return $definition;
    }

    private function getParentOptionsResolver(ResolvedFormTypeInterface $type)
    {
        $this->parents[$class = get_class($type->getInnerType())] = array();

        if (null !== $type->getParent()) {
            $optionsResolver = clone $this->getParentOptionsResolver($type->getParent());
        } else {
            $optionsResolver = new OptionsResolver();
        }

        $inheritedOptions = $optionsResolver->getDefinedOptions();
        $type->getInnerType()->configureOptions($optionsResolver);
        $this->parents[$class] = array_diff($optionsResolver->getDefinedOptions(), $inheritedOptions);

        $this->collectTypeExtensionsOptions($type, $optionsResolver);

        return $optionsResolver;
    }

    private function collectTypeExtensionsOptions(ResolvedFormTypeInterface $type, OptionsResolver $optionsResolver)
    {
        foreach ($type->getTypeExtensions() as $extension) {
            $inheritedOptions = $optionsResolver->getDefinedOptions();
            $extension->configureOptions($optionsResolver);
            $this->extensions[get_class($extension)] = array_diff($optionsResolver->getDefinedOptions(), $inheritedOptions);
        }
    }
}
