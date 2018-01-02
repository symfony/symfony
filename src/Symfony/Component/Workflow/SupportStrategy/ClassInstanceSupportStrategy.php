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

@trigger_error(sprintf('"%s" is deprecated since Symfony 4.1. Use "%s" instead.', ClassInstanceSupportStrategy::class, InstanceOfSupportStrategy::class), E_USER_DEPRECATED);

use Symfony\Component\Workflow\Workflow;

/**
 * @author Andreas Kleemann <akleemann@inviqa.com>
 *
 * @deprecated since Symfony 4.1, to be removed in 5.0. Use InstanceOfSupportStrategy instead
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
