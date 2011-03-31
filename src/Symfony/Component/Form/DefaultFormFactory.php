<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\HttpFoundation\File\TemporaryStorage;
use Symfony\Component\Validator\ValidatorInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Renderer\Loader\ArrayRendererFactoryLoader;
use Symfony\Component\Form\Renderer\Loader\RendererFactoryLoaderInterface;
use Symfony\Component\Form\Renderer\ThemeRendererFactory;
use Symfony\Component\Form\Type;
use Symfony\Component\Form\Type\FormTypeInterface;
use Symfony\Component\Form\Type\AbstractFieldType;
use Symfony\Component\Form\Type\Loader\TypeLoaderInterface;
use Symfony\Component\Form\Type\Loader\DefaultTypeLoader;
use Symfony\Component\Form\Renderer\Theme\ThemeFactoryInterface;
use Symfony\Component\Form\Renderer\Theme\PhpThemeFactory;
use Symfony\Component\Form\CsrfProvider\CsrfProviderInterface;
use Symfony\Component\Form\CsrfProvider\DefaultCsrfProvider;

/**
 * Default Form Factory simplifies the construction and usage of the form component in a non-dependency injection context.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class DefaultFormFactory extends FormFactory
{
    /**
     * Create a default form factory from as few inputs as necessary.
     *
     * NOTICE: Make the csrf secret and storage secret longer rather than shorter, but keep them constant across all requests!
     *
     * @param ValidatorInterface $validator
     * @param string $csrfSecret
     * @param string $storageSecret
     * @param string $charset
     *
     * @return DefaultFormFactory
     */
    public static function createDefault(ValidatorInterface $validator, $csrfSecret, $storageSecret, $charset = 'UTF-8')
    {
        $csrfProvider = new DefaultCsrfProvider($csrfSecret);
        $tempStorage = new TemporaryStorage($storageSecret);
        $rendererFactoryLoader = new ArrayRendererFactoryLoader(array('php' => new ThemeRendererFactory(new PhpThemeFactory($charset))));

        return self::createInstance($rendererFactoryLoader, $validator, $csrfProvider, $tempStorage);
    }

    /**
     * Factory method to simplify creation of a default form factory.
     *
     * @param RendererFactoryLoaderInterface $rendererFactoryLoader
     * @param ValidatorInterface $validator
     * @param CsrfProviderInterface $crsfProvider
     * @param TemporaryStorage $tempStorage
     *
     * @return DefaultFormFactory
     */
    public static function createInstance(RendererFactoryLoaderInterface $rendererFactoryLoader,
            ValidatorInterface $validator,
            CsrfProviderInterface $crsfProvider,
            TemporaryStorage $tempStorage)
    {
        $typeLoader = new DefaultTypeLoader($validator, $crsfProvider, $tempStorage);

        return new self($typeLoader, $rendererFactoryLoader);
    }

    /**
     * @var TypeLoaderInterface
     */
    private $typeLoader;

    /**
     * @param TypeLoaderInterface $typeLoader
     * @param RendererFactoryLoaderInterface $rendererFactoryLoader
     */
    public function __construct(TypeLoaderInterface $typeLoader, RendererFactoryLoaderInterface $rendererFactoryLoader)
    {
        $this->typeLoader = $typeLoader;

        parent::__construct($typeLoader, $rendererFactoryLoader);
    }

    /**
     * @param AbstractFieldType $config
     */
    public function addConfig(AbstractFieldType $config)
    {
        $this->typeLoader->addConfig($config);
    }
}