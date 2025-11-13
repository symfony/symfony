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
    private $_hasDeprecatedCalls = false;

    /**
     * @deprecated since Symfony 7.4
     */
    public function translator(array $value = []): \Symfony\Config\AddToList\TranslatorConfig
    {
        $this->_hasDeprecatedCalls = true;
        if (null === $this->translator) {
            $this->_usedProperties['translator'] = true;
            $this->translator = new \Symfony\Config\AddToList\TranslatorConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "translator()" has already been initialized. You cannot pass values the second time you call translator().');
        }

        return $this->translator;
    }

    /**
     * @deprecated since Symfony 7.4
     */
    public function messenger(array $value = []): \Symfony\Config\AddToList\MessengerConfig
    {
        $this->_hasDeprecatedCalls = true;
        if (null === $this->messenger) {
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

    public function __construct(array $config = [])
    {
        if (array_key_exists('translator', $config)) {
            $this->_usedProperties['translator'] = true;
            $this->translator = new \Symfony\Config\AddToList\TranslatorConfig($config['translator']);
            unset($config['translator']);
        }

        if (array_key_exists('messenger', $config)) {
            $this->_usedProperties['messenger'] = true;
            $this->messenger = new \Symfony\Config\AddToList\MessengerConfig($config['messenger']);
            unset($config['messenger']);
        }

        if ($config) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($config)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['translator'])) {
            $output['translator'] = $this->translator->toArray();
        }
        if (isset($this->_usedProperties['messenger'])) {
            $output['messenger'] = $this->messenger->toArray();
        }
        if ($this->_hasDeprecatedCalls) {
            trigger_deprecation('symfony/config', '7.4', 'Calling any fluent method on "%s" is deprecated; pass the configuration to the constructor instead.', $this::class);
        }

        return $output;
    }

}
