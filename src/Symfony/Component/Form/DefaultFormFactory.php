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
use Symfony\Component\Form\Type;
use Symfony\Component\Form\Type\FormTypeInterface;
use Symfony\Component\Form\Renderer\ThemeEngine\FormThemeEngineInterface;
use Symfony\Component\Form\Type\Loader\TypeLoaderInterface;
use Symfony\Component\Form\Type\Loader\DefaultTypeLoader;
use Symfony\Component\Form\Type\AbstractFieldType;
use Symfony\Component\Form\CsrfProvider\CsrfProviderInterface;
use Symfony\Component\Form\CsrfProvider\DefaultCsrfProvider;
use Doctrine\ORM\EntityManager;

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
     * @param \Doctrine\ORM\EntityManager $entityManager
     * @return DefaultFormFactory
     */
    public static function createDefault(ValidatorInterface $validator, $csrfSecret, $storageSecret, $charset = 'UTF-8', $entityManager = null)
    {
        $csrfProvider = new DefaultCsrfProvider($csrfSecret);
        $tempStorage = new TemporaryStorage($storageSecret);
        $defaultTheme = new PhpThemeEngine($charset);
        return self::createInstance($defaultTheme, $validator, $csrfProvider, $tempStorage, $entityManager);
    }

    /**
     * Factory method to simplify creation of a default form factory.
     * 
     * @param FormThemeEngineInterface $theme
     * @param ValidatorInterface $validator
     * @param CsrfProviderInterface $crsfProvider
     * @param TemporaryStorage $tempStorage
     * @param \Doctrine\ORM\EntityManager $entityManager
     * @return DefaultFormFactory
     */
    public static function createInstance(FormThemeEngineInterface $theme,
            ValidatorInterface $validator,
            CsrfProviderInterface $crsfProvider,
            TemporaryStorage $tempStorage,
            $entityManager = null)
    {
        $typeLoader = new DefaultTypeLoader();
        $factory = new self($typeLoader);
        $typeLoader->initialize($factory, $theme, $crsfProvider, $validator, $tempStorage, $entityManager);

        return $factory;
    }

    /**
     * @var TypeLoaderInterface
     */
    private $typeLoader;

    /**
     * @param TypeLoaderInterface $typeLoader
     */
    public function __construct(TypeLoaderInterface $typeLoader)
    {
        $this->typeLoader = $typeLoader;
        parent::__construct($typeLoader);
    }

    /**
     * @param AbstractFieldType $config
     */
    public function addConfig(AbstractFieldType $config)
    {
        $this->typeLoader->addConfig($config);
    }
}