<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Workflow\SupportStrategy;

@trigger_error(sprintf('"%s" is deprecated since Symphony 4.1. Use "%s" instead.', ClassInstanceSupportStrategy::class, InstanceOfSupportStrategy::class), E_USER_DEPRECATED);

use Symphony\Component\Workflow\Workflow;

/**
 * @author Andreas Kleemann <akleemann@inviqa.com>
 *
 * @deprecated since Symphony 4.1, use InstanceOfSupportStrategy instead
 */
final class ClassInstanceSupportStrategy implements SupportStrategyInterface
{
    private $className;

    public function __construct(string $className)
    {
        $this->className = $className;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(Workflow $workflow, $subject)
    {
        return $subject instanceof $this->className;
    }

    /**
     * @return string
     */
    public function getClassName()
    {
        return $this->className;
    }
}
