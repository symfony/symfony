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
     * @var int
     */
    protected $circularReferenceLimit = 1;

    /**
     * @var callable
     */
    protected $circularReferenceHandler;

    /**
     * Set circular reference limit.
     *
     * @param int $circularReferenceLimit Limit of iterations for the same object
     *
     * @return self
     */
    public function setCircularReferenceLimit($circularReferenceLimit)
    {
        $this->circularReferenceLimit = $circularReferenceLimit;

        return $this;
    }

    /**
     * Set circular reference handler.
     *
     * @param callable $circularReferenceHandler
     *
     * @return self
     */
    public function setCircularReferenceHandler(callable $circularReferenceHandler)
    {
        $this->circularReferenceHandler = $circularReferenceHandler;

        return $this;
    }

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

        $circularReferenceLimitField = $this->getCircularReferenceLimitField();
        if (isset($context[$circularReferenceLimitField][$objectHash])) {
            if ($context[$circularReferenceLimitField][$objectHash] >= $this->circularReferenceLimit) {
                unset($context[$circularReferenceLimitField][$objectHash]);

                return true;
            }

            ++$context[$circularReferenceLimitField][$objectHash];
        } else {
            $context[$circularReferenceLimitField][$objectHash] = 1;
        }

        return false;
    }

    /**
     * Handles a circular reference.
     *
     * If a circular reference handler is set, it will be called. Otherwise, a
     * {@class CircularReferenceException} will be thrown.
     *
     * @param object $object
     *
     * @return mixed
     *
     * @throws CircularReferenceException
     */
    protected function handleCircularReference($object)
    {
        if ($this->circularReferenceHandler) {
            return \call_user_func($this->circularReferenceHandler, $object);
        }

        throw new CircularReferenceException(sprintf('A circular reference has been detected when serializing the object of class "%s" (configured limit: %d)', \get_class($object), $this->circularReferenceLimit));
    }

    private function getCircularReferenceLimitField()
    {
        return ObjectNormalizer::CIRCULAR_REFERENCE_LIMIT;
    }
}
