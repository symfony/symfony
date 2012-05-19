<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Validator\ViolationMapper;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Exception\ErrorMappingException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormMapping
{
    /**
     * @var FormInterface
     */
    private $origin;

    /**
     * @var FormInterface
     */
    private $target;

    /**
     * @var string
     */
    private $targetPath;

    public function __construct(FormInterface $origin, $targetPath)
    {
        $this->origin = $origin;
        $this->targetPath = $targetPath;
    }

    /**
     * @return FormInterface
     */
    public function getOrigin()
    {
        return $this->origin;
    }

    /**
     * @return FormInterface
     *
     * @throws ErrorMappingException
     */
    public function getTarget()
    {
        // Lazy initialization to make sure that the constructor is cheap
        if (null === $this->target) {
            $childNames = explode('.', $this->targetPath);
            $target = $this->origin;

            foreach ($childNames as $childName) {
                if (!$target->has($childName)) {
                    throw new ErrorMappingException(sprintf('The child "%s" of "%s" mapped by the rule "%s" in "%s" does not exist.', $childName, $target->getName(), $this->targetPath, $this->origin->getName()));
                }
                $target = $target->get($childName);
            }

            // Only set once successfully resolved
            $this->target = $target;
        }

        return $this->target;
    }
}
