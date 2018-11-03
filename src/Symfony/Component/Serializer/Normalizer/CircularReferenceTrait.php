<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Normalizer;

use Symfony\Component\Serializer\Exception\CircularReferenceException;

/**
 * Handle circular references.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 *
 * @internal
 */
trait CircularReferenceTrait
{
    /**
     * @deprecated since Symfony 4.2
     */
    protected $circularReferenceLimit = 1;

    /**
     * @deprecated since Symfony 4.2
     *
     * @var callable|null
     */
    protected $circularReferenceHandler;

    /**
     * Detects if the configured circular reference limit is reached.
     *
     * @param object $object
     * @param array  $context
     *
     * @return bool
     *
     * @throws CircularReferenceException
     */
    protected function isCircularReference($object, &$context)
    {
        $objectHash = spl_object_hash($object);

        $circularReferenceLimit = $context[AbstractNormalizer::CIRCULAR_REFERENCE_LIMIT] ?? $this->defaultContext[AbstractNormalizer::CIRCULAR_REFERENCE_LIMIT] ?? $this->circularReferenceLimit;
        if (isset($context[AbstractNormalizer::CIRCULAR_REFERENCE_LIMIT_COUNTERS][$objectHash])) {
            if ($context[AbstractNormalizer::CIRCULAR_REFERENCE_LIMIT_COUNTERS][$objectHash] >= $circularReferenceLimit) {
                unset($context[AbstractNormalizer::CIRCULAR_REFERENCE_LIMIT_COUNTERS][$objectHash]);

                return true;
            }

            ++$context[AbstractNormalizer::CIRCULAR_REFERENCE_LIMIT_COUNTERS][$objectHash];
        } else {
            $context[AbstractNormalizer::CIRCULAR_REFERENCE_LIMIT_COUNTERS][$objectHash] = 1;
        }

        return false;
    }

    /**
     * Handles a circular reference.
     *
     * If a circular reference handler is set, it will be called. Otherwise, a
     * {@class CircularReferenceException} will be thrown.
     *
     * @final since Symfony 4.2
     *
     * @param object      $object
     * @param string|null $format
     * @param array       $context
     *
     * @return mixed
     *
     * @throws CircularReferenceException
     */
    protected function handleCircularReference($object/*, string $format = null, array $context = array()*/)
    {
        if (\func_num_args() < 2 && __CLASS__ !== \get_class($this) && __CLASS__ !== (new \ReflectionMethod($this, __FUNCTION__))->getDeclaringClass()->getName() && !$this instanceof \PHPUnit\Framework\MockObject\MockObject && !$this instanceof \Prophecy\Prophecy\ProphecySubjectInterface) {
            @trigger_error(sprintf('The "%s()" method will have two new "string $format = null" and "array $context = array()" arguments in version 5.0, not defining it is deprecated since Symfony 4.2.', __METHOD__), E_USER_DEPRECATED);
        }
        $format = \func_num_args() > 1 ? func_get_arg(1) : null;
        $context = \func_num_args() > 2 ? func_get_arg(2) : array();

        $circularReferenceHandler = $context[AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER] ?? $this->defaultContext[AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER] ?? $this->circularReferenceHandler;
        if ($circularReferenceHandler) {
            return $circularReferenceHandler($object, $format, $context);
        }

        throw new CircularReferenceException(sprintf('A circular reference has been detected when serializing the object of class "%s" (configured limit: %d)', \get_class($object), $this->circularReferenceLimit));
    }
}
