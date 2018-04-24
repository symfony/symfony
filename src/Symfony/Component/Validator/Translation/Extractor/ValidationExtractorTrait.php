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

use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\MemberMetadata;

/**
 * Provides common methods for extractors of validation messages.
 *
 * @author Webnet team <comptewebnet@webnet.fr>
 */
trait ValidationExtractorTrait
{
    /**
     * Prefix for found messages.
     *
     * @var string
     */
    private $prefix = '';

    /**
     * Default domain for found messages.
     *
     * @var string
     */
    private $defaultDomain = 'validators';

    /**
     * {@inheritdoc}
     */
    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
    }

    /**
     * Fill catalogue with validation messages from metadata.
     *
     * @param MessageCatalogue $catalogue
     * @param ClassMetadata    $metadata
     *
     * @throws \ReflectionException
     */
    private function extractMessagesFromMetadata(MessageCatalogue $catalogue, ClassMetadata $metadata)
    {
        // extract messages from constrained properties
        foreach ($metadata->getConstrainedProperties() as $constrainedProperty) {
            foreach ($metadata->getPropertyMetadata($constrainedProperty) as $propertyMetadata) {
                /** @var $propertyMetadata MemberMetadata */
                foreach ($propertyMetadata->getConstraints() as $constraint) {
                    $messages = $this->getMessagesFromConstraint($constraint);
                    $this->addMessagesToCatalog($catalogue, $messages);
                }
            }
        }

        // extract messages from callback constraints
        foreach ($metadata->getConstraints() as $constraint) {
            if (isset($constraint->callback)) {
                $extractor = new CallbackValidationMessagesExtractor($metadata->getReflectionClass()->getFileName(), $metadata->getClassName(), $constraint->callback);
                $this->addMessagesToCatalog($catalogue, $extractor->extractMessages());
            }
        }
    }

    /**
     * Add messages to catalog.
     *
     * @param MessageCatalogueInterface $catalogue
     * @param string[]                  $messages
     */
    private function addMessagesToCatalog(MessageCatalogueInterface $catalogue, $messages)
    {
        foreach ($messages as $message) {
            $catalogue->set(trim($message), $this->prefix.trim($message), $this->defaultDomain);
        }
    }

    /**
     * Get all validation messages from given constraint.
     *
     * @param Constraint $constraint
     *
     * @return array
     *
     * @throws \ReflectionException
     */
    private function getMessagesFromConstraint(Constraint $constraint)
    {
        $messages = array();

        $refl = new \ReflectionClass($constraint);

        foreach ($refl->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if (preg_match('/message/i', $property->name) && is_string($message = $property->getValue($constraint))) {
                $messages[] = $message;
            }
        }

        return $messages;
    }
}
