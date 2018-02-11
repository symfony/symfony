<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection;

use Doctrine\Common\Annotations\Reader;
use Psr\Cache\CacheItemPoolInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Debug\AutowiringInfoProviderInterface;
use Symfony\Component\DependencyInjection\Debug\AutowiringTypeInfo;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Workflow\Registry;

/**
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
final class FrameworkAutowiringInfoProvider implements AutowiringInfoProviderInterface
{
    public function getTypeInfos(): array
    {
        return array(
            AutowiringTypeInfo::create(CacheItemPoolInterface::class, 'Cache', 10)
                ->setDescription('general-purpose service for caching things'),

            AutowiringTypeInfo::create(CacheInterface::class, 'Simple Cache', 10)
                ->setDescription('simpler cache, but less features'),

            AutowiringTypeInfo::create(RouterInterface::class, 'Router', 10)
                ->setDescription('used to generate URLs'),

            AutowiringTypeInfo::create(EventDispatcherInterface::class, 'Event Dispatcher')
                ->setDescription('used to dispatch custom events'),

            AutowiringTypeInfo::create(Reader::class, 'Annotation Reader', -10),

            AutowiringTypeInfo::create(ParameterBagInterface::class, 'Parameter Bag')
                ->setDescription('access service parameters'),

            AutowiringTypeInfo::create(Filesystem::class, 'Filesystem')
                ->setDescription('helper for filesystem actions'),

            AutowiringTypeInfo::create(RequestStack::class, 'Request Stack')
                ->setDescription('access the Request object'),

            AutowiringTypeInfo::create(SessionInterface::class, 'Session'),

            AutowiringTypeInfo::create(FlashBagInterface::class, 'Flash Bag')
                ->setDescription('use to set temporary success/failure messages'),

            AutowiringTypeInfo::create(KernelInterface::class, 'Kernel'),

            AutowiringTypeInfo::create(Stopwatch::class, 'Stopwatch')
                ->setDescription('use to add custom timings to profiler'),

            AutowiringTypeInfo::create(Packages::class, 'Asset Packages')
                ->setDescription('use to generate URLs to assets'),

            AutowiringTypeInfo::create(FormFactoryInterface::class, 'Form Factory')
                ->setDescription('use to create form objects'),

            AutowiringTypeInfo::create(ValidatorInterface::class, 'Validator')
                ->setDescription('use to validate data against some constraints'),

            AutowiringTypeInfo::create(TranslatorInterface::class, 'Translator'),

            AutowiringTypeInfo::create(PropertyAccessorInterface::class, 'Property Accessor')
                ->setDescription('use to read dynamic keys from some data'),

            AutowiringTypeInfo::create(CsrfTokenManagerInterface::class, 'CSRF Token Manager')
                ->setDescription('generate and check CSRF tokens'),

            AutowiringTypeInfo::create(SerializerInterface::class, 'Serializer')
                ->setDescription('use to serialize data to JSON, XML, etc'),

            AutowiringTypeInfo::create(Registry::class, 'Workflow')
                ->setDescription('use to fetch workflows'),

            AutowiringTypeInfo::create(Registry::class, 'Workflow')
                ->setDescription('use to fetch workflows'),
        );
    }
}
