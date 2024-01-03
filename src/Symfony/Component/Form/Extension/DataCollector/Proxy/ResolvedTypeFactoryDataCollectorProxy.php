<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\DataCollector\Proxy;

use Symfony\Component\Form\Extension\DataCollector\FormDataCollectorInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\ResolvedFormTypeFactoryInterface;
use Symfony\Component\Form\ResolvedFormTypeInterface;

/**
 * Proxy that wraps resolved types into {@link ResolvedTypeDataCollectorProxy}
 * instances.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ResolvedTypeFactoryDataCollectorProxy implements ResolvedFormTypeFactoryInterface
{
    private ResolvedFormTypeFactoryInterface $proxiedFactory;
    private FormDataCollectorInterface $dataCollector;

    public function __construct(ResolvedFormTypeFactoryInterface $proxiedFactory, FormDataCollectorInterface $dataCollector)
    {
        $this->proxiedFactory = $proxiedFactory;
        $this->dataCollector = $dataCollector;
    }

    public function createResolvedType(FormTypeInterface $type, array $typeExtensions, ResolvedFormTypeInterface $parent = null): ResolvedFormTypeInterface
    {
        return new ResolvedTypeDataCollectorProxy(
            $this->proxiedFactory->createResolvedType($type, $typeExtensions, $parent),
            $this->dataCollector
        );
    }
}
