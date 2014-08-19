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
 * Validates values against constraints defined in {@link MetadataInterface}
 * instances.
 *
 * This interface is an implementation of the Visitor design pattern. A value
 * is validated by first passing it to the {@link validate} method. That method
 * will determine the matching {@link MetadataInterface} for validating the
 * value. It then calls the {@link MetadataInterface::accept} method of that
 * metadata. <tt>accept()</tt> does two things:
 *
 * <ol>
 * <li>It calls {@link visit} to validate the value against the constraints of
 * the metadata.</li>
 * <li>It calls <tt>accept()</tt> on all nested metadata instances with the
 * corresponding values extracted from the current value. For example, if the
 * current metadata represents a class and the current value is an object of
 * that class, the metadata contains nested instances for each property of that
 * class. It forwards the call to these nested metadata with the values of the
 * corresponding properties in the original object.</li>
 * </ol>
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated Deprecated since version 2.5, to be removed in Symfony 3.0.
 */
interface ValidationVisitorInterface
{
    /**
     * Validates a value.
     *
     * If the value is an array or a traversable object, you can set the
     * parameter <tt>$traverse</tt> to <tt>true</tt> in order to run through
     * the collection and validate each element. If these elements can be
     * collections again and you want to traverse them recursively, set the
     * parameter <tt>$deep</tt> to <tt>true</tt> as well.
     *
     * If you set <tt>$traversable</tt> to <tt>true</tt>, the visitor will
     * nevertheless try to find metadata for the collection and validate its
     * constraints. If no such metadata is found, the visitor ignores that and
     * only iterates the collection.
     *
     * If you don't set <tt>$traversable</tt> to <tt>true</tt> and the visitor
     * does not find metadata for the given value, it will fail with an
     * exception.
     *
     * @param mixed   $value        The value to validate.
     * @param string  $group        The validation group to validate.
     * @param string  $propertyPath The current property path in the validation graph.
     * @param bool    $traverse     Whether to traverse the value if it is traversable.
     * @param bool    $deep         Whether to traverse nested traversable values recursively.
     *
     * @throws Exception\NoSuchMetadataException If no metadata can be found for
     *                                           the given value.
     *
     * @deprecated Deprecated since version 2.5, to be removed in Symfony 3.0.
     */
    public function validate($value, $group, $propertyPath, $traverse = false, $deep = false);

    /**
     * Validates a value against the constraints defined in some metadata.
     *
     * This method implements the Visitor design pattern. See also
     * {@link ValidationVisitorInterface}.
     *
     * @param MetadataInterface $metadata     The metadata holding the constraints.
     * @param mixed             $value        The value to validate.
     * @param string            $group        The validation group to validate.
     * @param string            $propertyPath The current property path in the validation graph.
     *
     * @deprecated Deprecated since version 2.5, to be removed in Symfony 3.0.
     */
    public function visit(MetadataInterface $metadata, $value, $group, $propertyPath);
}
