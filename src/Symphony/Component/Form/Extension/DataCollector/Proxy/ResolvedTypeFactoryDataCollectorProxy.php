<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Extension\DataCollector\Proxy;

use Symphony\Component\Form\Extension\DataCollector\FormDataCollectorInterface;
use Symphony\Component\Form\FormTypeInterface;
use Symphony\Component\Form\ResolvedFormTypeFactoryInterface;
use Symphony\Component\Form\ResolvedFormTypeInterface;

/**
 * Proxy that wraps resolved types into {@link ResolvedTypeDataCollectorProxy}
 * instances.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ResolvedTypeFactoryDataCollectorProxy implements ResolvedFormTypeFactoryInterface
{
    private $proxiedFactory;
    private $dataCollector;

    public function __construct(ResolvedFormTypeFactoryInterface $proxiedFactory, FormDataCollectorInterface $dataCollector)
    {
        $this->proxiedFactory = $proxiedFactory;
        $this->dataCollector = $dataCollector;
    }

    /**
     * {@inheritdoc}
     */
    public function createResolvedType(FormTypeInterface $type, array $typeExtensions, ResolvedFormTypeInterface $parent = null)
    {
        return new ResolvedTypeDataCollectorProxy(
            $this->proxiedFactory->createResolvedType($type, $typeExtensions, $parent),
            $this->dataCollector
        );
    }
}
