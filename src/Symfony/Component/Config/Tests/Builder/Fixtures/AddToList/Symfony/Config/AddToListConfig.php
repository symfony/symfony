<?php

namespace Symfony\Config;

require_once __DIR__.\DIRECTORY_SEPARATOR.'AddToList'.\DIRECTORY_SEPARATOR.'TranslatorConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'AddToList'.\DIRECTORY_SEPARATOR.'MessengerConfig.php';

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class AddToListConfig implements \Symfony\Component\Config\Builder\ConfigBuilderInterface
{
    private $translator;
    private $messenger;
    private $_usedProperties = [];

    /**
     * @template TValue
     * @param TValue $value
     * @return \Symfony\Config\AddToList\TranslatorConfig|$this
     * @psalm-return (TValue is array ? \Symfony\Config\AddToList\TranslatorConfig : static)
     */
    public function translator(mixed $value = []): \Symfony\Config\AddToList\TranslatorConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['translator'] = true;
            $this->translator = $value;

            return $this;
        }

        if (!$this->translator instanceof \Symfony\Config\AddToList\TranslatorConfig) {
            $this->_usedProperties['translator'] = true;
            $this->translator = new \Symfony\Config\AddToList\TranslatorConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "translator()" has already been initialized. You cannot pass values the second time you call translator().');
        }

        return $this->translator;
    }

    /**
     * @template TValue
     * @param TValue $value
     * @return \Symfony\Config\AddToList\MessengerConfig|$this
     * @psalm-return (TValue is array ? \Symfony\Config\AddToList\MessengerConfig : static)
     */
    public function messenger(mixed $value = []): \Symfony\Config\AddToList\MessengerConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['messenger'] = true;
            $this->messenger = $value;

            return $this;
        }

        if (!$this->messenger instanceof \Symfony\Config\AddToList\MessengerConfig) {
            $this->_usedProperties['messenger'] = true;
            $this->messenger = new \Symfony\Config\AddToList\MessengerConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "messenger()" has already been initialized. You cannot pass values the second time you call messenger().');
        }

        return $this->messenger;
    }

    public function getExtensionAlias(): string
    {
        return 'add_to_list';
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('translator', $value)) {
            $this->_usedProperties['translator'] = true;
            $this->translator = \is_array($value['translator']) ? new \Symfony\Config\AddToList\TranslatorConfig($value['translator']) : $value['translator'];
            unset($value['translator']);
        }

        if (array_key_exists('messenger', $value)) {
            $this->_usedProperties['messenger'] = true;
            $this->messenger = \is_array($value['messenger']) ? new \Symfony\Config\AddToList\MessengerConfig($value['messenger']) : $value['messenger'];
            unset($value['messenger']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['translator'])) {
            $output['translator'] = $this->translator instanceof \Symfony\Config\AddToList\TranslatorConfig ? $this->translator->toArray() : $this->translator;
        }
        if (isset($this->_usedProperties['messenger'])) {
            $output['messenger'] = $this->messenger instanceof \Symfony\Config\AddToList\MessengerConfig ? $this->messenger->toArray() : $this->messenger;
        }

        return $output;
    }

}
