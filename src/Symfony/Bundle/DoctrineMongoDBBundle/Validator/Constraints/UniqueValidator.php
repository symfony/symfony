<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineMongoDBBundle\Validator\Constraints;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Proxy\Proxy;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Doctrine MongoDB ODM unique value validator.
 *
 * @author Bulat Shakirzyanov <bulat@theopenskyproject.com>
 */
class UniqueValidator extends ConstraintValidator
{

    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @param Doctrine\ODM\MongoDB\Document $value
     * @param Constraint $constraint
     * @return Boolean
     */
    public function isValid($document, Constraint $constraint)
    {
        $class    = get_class($document);
        $dm       = $this->getDocumentManager($constraint);
        $metadata = $dm->getClassMetadata($class);

        if ($metadata->isEmbeddedDocument) {
            throw new \InvalidArgumentException(sprintf("Document '%s' is an embedded document, and cannot be validated", $class));
        }

        $query = $this->getQueryArray($metadata, $document, $constraint->path);

        // check if document exists in mongodb
        if (null === ($doc = $dm->getRepository($class)->findOneBy($query))) {
            return true;
        }

        // check if document in mongodb is the same document as the checked one
        if ($doc === $document) {
            return true;
        }

        // check if returned document is proxy and initialize the minimum identifier if needed
        if ($doc instanceof Proxy) {
            $metadata->setIdentifierValue($doc, $doc->__identifier);
        }

        // check if document has the same identifier as the current one
        if ($metadata->getIdentifierValue($doc) === $metadata->getIdentifierValue($document)) {
            return true;
        }

        $this->context->setPropertyPath($this->context->getPropertyPath() . '.' . $constraint->path);
        $this->setMessage($constraint->message, array(
            '{{ property }}' => $constraint->path,
        ));
        return false;
    }

    protected function getQueryArray(ClassMetadata $metadata, $document, $path)
    {
        $class = $metadata->name;
        $field = $this->getFieldNameFromPropertyPath($path);
        if (!isset($metadata->fieldMappings[$field])) {
            throw new \LogicException('Mapping for \'' . $path . '\' doesn\'t exist for ' . $class);
        }
        $mapping = $metadata->fieldMappings[$field];
        if (isset($mapping['reference']) && $mapping['reference']) {
            throw new \LogicException('Cannot determine uniqueness of referenced document values');
        }
        switch ($mapping['type']) {
            case 'one':
                // TODO: implement support for embed one documents
            case 'many':
                // TODO: implement support for embed many documents
                throw new \RuntimeException('Not Implemented.');
            case 'hash':
                $value = $metadata->getFieldValue($document, $mapping['fieldName']);
                return array($path => $this->getFieldValueRecursively($path, $value));
            case 'collection':
                return array($mapping['fieldName'] => array('$in' => $metadata->getFieldValue($document, $mapping['fieldName'])));
            default:
                return array($mapping['fieldName'] => $metadata->getFieldValue($document, $mapping['fieldName']));
        }
    }

    /**
     * Returns the actual document field value
     *
     * E.g. document.someVal -> document
     *      user.emails      -> user
     *      username         -> username
     *
     * @param string $field
     * @return string
     */
    private function getFieldNameFromPropertyPath($field)
    {
        $pieces = explode('.', $field);
        return $pieces[0];
    }

    private function getFieldValueRecursively($fieldName, $value)
    {
        $pieces = explode('.', $fieldName);
        unset($pieces[0]);
        foreach ($pieces as $piece) {
            $value = $value[$piece];
        }
        return $value;
    }

    private function getDocumentManager(Unique $constraint)
    {
        return $this->container->get($constraint->getDocumentManagerId());
    }

}
