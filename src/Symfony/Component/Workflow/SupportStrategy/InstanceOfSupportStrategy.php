<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\SupportStrategy;

use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * @author Carlos Pereira De Amorim <carlos@shauri.fr>
 * @author Andreas Kleemann <akleemann@inviqa.com>
 * @author Amrouche Hamza <hamza.simperfit@gmail.com>
 */
final class InstanceOfSupportStrategy implements WorkflowSupportStrategyInterface
{
    private $className;

    public function __construct(string $className)
    {
        $this->className = $className;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(WorkflowInterface $workflow, $subject): bool
    {
        if (\is_object($subject)) {
            return $subject instanceof $this->className;
        }
        
        if (\is_string($subject)) {
            return $subject === $this->className;
        }

        throw new \InvalidArgumentException(sprintf('"%s" is not a supported type.', \gettype($subject)));
    }

    public function getClassName(): string
    {
        return $this->className;
    }
}
