<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\DataMapper;

use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class SimpleObjectMapper implements DataMapperInterface
{
    /**
     * @var FormDataToObjectConverterInterface
     */
    private $converter;

    /**
     * @var DataMapperInterface|null
     */
    private $originalMapper;

    /**
     * @param FormDataToObjectConverterInterface $converter
     * @param DataMapperInterface|null           $originalMapper
     */
    public function __construct(FormDataToObjectConverterInterface $converter, DataMapperInterface $originalMapper = null)
    {
        $this->converter = $converter;
        $this->originalMapper = $originalMapper;
    }

    /**
     * {@inheritdoc}
     */
    public function mapDataToForms($data, $forms)
    {
        // Fallback to original mapper instance or default to "PropertyPathMapper"
        // mapper implementation if not an "ObjectToFormDataConverterInterface" instance:
        if (!$this->converter instanceof ObjectToFormDataConverterInterface) {
            $propertyPathMapper = $this->originalMapper ?: new PropertyPathMapper();
            $propertyPathMapper->mapDataToForms($data, $forms);

            return;
        }

        if (!is_object($data) && null !== $data) {
            throw new UnexpectedTypeException($data, 'object or null');
        }

        $data = $this->converter->convertObjectToFormData($data);

        if (!is_array($data)) {
            throw new UnexpectedTypeException($data, 'array');
        }

        foreach ($forms as $form) {
            $config = $form->getConfig();

            if ($config->getMapped() && isset($data[$form->getName()])) {
                $form->setData($data[$form->getName()]);

                continue;
            }

            $form->setData($config->getData());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function mapFormsToData($forms, &$data)
    {
        $fieldsData = array();
        foreach ($forms as $form) {
            $fieldsData[$form->getName()] = $form->getData();
        }

        $data = $this->converter->convertFormDataToObject($fieldsData, $data);
    }
}
