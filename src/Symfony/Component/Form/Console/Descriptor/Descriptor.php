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
    protected $ownOptions = [];
    protected $overriddenOptions = [];
    protected $parentOptions = [];
    protected $extensionOptions = [];
    protected $requiredOptions = [];
    protected $parents = [];
    protected $extensions = [];

    /**
     * {@inheritdoc}
     */
    public function describe(OutputInterface $output, $object, array $options = [])
    {
        $this->output = $output instanceof OutputStyle ? $output : new SymfonyStyle(new ArrayInput([]), $output);

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
                throw new \InvalidArgumentException(sprintf('Object of type "%s" is not describable.', get_debug_type($object)));
        }
    }

    abstract protected function describeDefaults(array $options);

    abstract protected function describeResolvedFormType(ResolvedFormTypeInterface $resolvedFormType, array $options = []);

    abstract protected function describeOption(OptionsResolver $optionsResolver, array $options);

    protected function collectOptions(ResolvedFormTypeInterface $type)
    {
        $this->parents = [];
        $this->extensions = [];

        if (null !== $type->getParent()) {
            $optionsResolver = clone $this->getParentOptionsResolver($type->getParent());
        } else {
            $optionsResolver = new OptionsResolver();
        }

        $type->getInnerType()->configureOptions($ownOptionsResolver = new OptionsResolverWrapper());
        $this->ownOptions = array_diff($ownOptionsResolver->getDefinedOptions(), $optionsResolver->getDefinedOptions());
        $overriddenOptions = array_intersect(array_merge($ownOptionsResolver->getDefinedOptions(), $ownOptionsResolver->getUndefinedOptions()), $optionsResolver->getDefinedOptions());

        $this->parentOptions = [];
        foreach ($this->parents as $class => $parentOptions) {
            $this->overriddenOptions[$class] = array_intersect($overriddenOptions, $parentOptions);
            $this->parentOptions[$class] = array_diff($parentOptions, $overriddenOptions);
        }

        $type->getInnerType()->configureOptions($optionsResolver);
        $this->collectTypeExtensionsOptions($type, $optionsResolver);
        $this->extensionOptions = [];
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

    protected function getOptionDefinition(OptionsResolver $optionsResolver, string $option)
    {
        $definition = [];

        if ($info = $optionsResolver->getInfo($option)) {
            $definition = [
                'info' => $info,
            ];
        }

        $definition += [
            'required' => $optionsResolver->isRequired($option),
            'deprecated' => $optionsResolver->isDeprecated($option),
        ];

        $introspector = new OptionsResolverIntrospector($optionsResolver);

        $map = [
            'default' => 'getDefault',
            'lazy' => 'getLazyClosures',
            'allowedTypes' => 'getAllowedTypes',
            'allowedValues' => 'getAllowedValues',
            'normalizers' => 'getNormalizers',
            'deprecation' => 'getDeprecation',
        ];

        foreach ($map as $key => $method) {
            try {
                $definition[$key] = $introspector->{$method}($option);
            } catch (NoConfigurationException $e) {
                // noop
            }
        }

        if (isset($definition['deprecation']) && isset($definition['deprecation']['message']) && \is_string($definition['deprecation']['message'])) {
            $definition['deprecationMessage'] = strtr($definition['deprecation']['message'], ['%name%' => $option]);
            $definition['deprecationPackage'] = $definition['deprecation']['package'];
            $definition['deprecationVersion'] = $definition['deprecation']['version'];
        }

        return $definition;
    }

    protected function filterOptionsByDeprecated(ResolvedFormTypeInterface $type)
    {
        $deprecatedOptions = [];
        $resolver = $type->getOptionsResolver();
        foreach ($resolver->getDefinedOptions() as $option) {
            if ($resolver->isDeprecated($option)) {
                $deprecatedOptions[] = $option;
            }
        }

        $filterByDeprecated = function (array $options) use ($deprecatedOptions) {
            foreach ($options as $class => $opts) {
                if ($deprecated = array_intersect($deprecatedOptions, $opts)) {
                    $options[$class] = $deprecated;
                } else {
                    unset($options[$class]);
                }
            }

            return $options;
        };

        $this->ownOptions = array_intersect($deprecatedOptions, $this->ownOptions);
        $this->overriddenOptions = $filterByDeprecated($this->overriddenOptions);
        $this->parentOptions = $filterByDeprecated($this->parentOptions);
        $this->extensionOptions = $filterByDeprecated($this->extensionOptions);
    }

    private function getParentOptionsResolver(ResolvedFormTypeInterface $type): OptionsResolver
    {
        $this->parents[$class = \get_class($type->getInnerType())] = [];

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
            $this->extensions[\get_class($extension)] = array_diff($optionsResolver->getDefinedOptions(), $inheritedOptions);
        }
    }
}
