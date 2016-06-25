<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator;

/**
 * A container for validation metadata.
 *
 * The container contains constraints that may belong to different validation
 * groups. Constraints for a specific group can be fetched by calling
 * {@link findConstraints}.
 *
 * Implement this interface to add validation metadata to your own metadata
 * layer. Each metadata may have named properties. Each property can be
 * represented by one or more {@link PropertyMetadataInterface} instances that
 * are returned by {@link getPropertyMetadata}. Since
 * <tt>PropertyMetadataInterface</tt> inherits from <tt>MetadataInterface</tt>,
 * each property may be divided into further properties.
 *
 * The {@link accept} method of each metadata implements the Visitor pattern.
 * The method should forward the call to the visitor's
 * {@link ValidationVisitorInterface::visit} method and additionally call
 * <tt>accept()</tt> on all structurally related metadata instances.
 *
 * For example, to store constraints for PHP classes and their properties,
 * create a class <tt>ClassMetadata</tt> (implementing <tt>MetadataInterface</tt>)
 * and a class <tt>PropertyMetadata</tt> (implementing <tt>PropertyMetadataInterface</tt>).
 * <tt>ClassMetadata::getPropertyMetadata($property)</tt> returns all
 * <tt>PropertyMetadata</tt> instances for a property of that class. Its
 * <tt>accept()</tt>-method simply forwards to <tt>ValidationVisitorInterface::visit()</tt>
 * and calls <tt>accept()</tt> on all contained <tt>PropertyMetadata</tt>
 * instances, which themselves call <tt>ValidationVisitorInterface::visit()</tt>
 * again.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated since version 2.5, to be removed in 3.0.
 *             Use {@link Mapping\MetadataInterface} instead.
 */
interface MetadataInterface
{
    /**
     * Implementation of the Visitor design pattern.
     *
     * Calls {@link ValidationVisitorInterface::visit} and then forwards the
     * <tt>accept()</tt>-call to all property metadata instances.
     *
     * @param ValidationVisitorInterface $visitor      The visitor implementing the validation logic
     * @param mixed                      $value        The value to validate
     * @param string|string[]            $group        The validation group to validate in
     * @param string                     $propertyPath The current property path in the validation graph
     *
     * @deprecated since version 2.5, to be removed in 3.0.
     */
    public function accept(ValidationVisitorInterface $visitor, $value, $group, $propertyPath);

    /**
     * Returns all constraints for a given validation group.
     *
     * @param string $group The validation group
     *
     * @return Constraint[] A list of constraint instances
     */
    public function findConstraints($group);
}
