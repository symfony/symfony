UPGRADE FROM 2.7 to 2.8
=======================

Form
----

 * The "cascade_validation" option was deprecated. Use the "constraints"
   option together with the `Valid` constraint instead. Contrary to
   "cascade_validation", "constraints" must be set on the respective child forms,
   not the parent form.
   
   Before:
   
   ```php
   $form = $this->createFormBuilder($article, array('cascade_validation' => true))
       ->add('author', new AuthorType())
       ->getForm();
   ```
   
   After:
   
   ```php
   use Symfony\Component\Validator\Constraints\Valid;
   
   $form = $this->createFormBuilder($article)
       ->add('author', new AuthorType(), array(
           'constraints' => new Valid(),
       ))
       ->getForm();
   ```
   
   Alternatively, you can set the `Valid` constraint in the model itself:
   
   ```php
   use Symfony\Component\Validator\Constraints as Assert;
   
   class Article
   {
       /**
        * @Assert\Valid
        */
       private $author;
   }
   ```
   
 * Type names were deprecated and will be removed in Symfony 3.0. Instead of
   referencing types by name, you should reference them by their
   fully-qualified class name (FQCN) instead. With PHP 5.5 or later, you can
   use the "class" constant for that:
   
   Before:
   
   ```php
   $form = $this->createFormBuilder()
       ->add('name', 'text')
       ->add('age', 'integer')
       ->getForm();
   ```
   
   After:
   
   ```php
   use Symfony\Component\Form\Extension\Core\Type\IntegerType;
   use Symfony\Component\Form\Extension\Core\Type\TextType;
   
   $form = $this->createFormBuilder()
       ->add('name', TextType::class)
       ->add('age', IntegerType::class)
       ->getForm();
   ```
   
   As a further consequence, the method `FormTypeInterface::getName()` was
   deprecated and will be removed in Symfony 3.0. You should remove this method
   from your form types.
   
   If you want to customize the block prefix of a type in Twig, you should now
   implement `FormTypeInterface::getBlockPrefix()` instead:
   
   Before:
   
   ```php
   class UserProfileType extends AbstractType
   {
       public function getName()
       {
           return 'profile';
       }
   }
   ```
   
   After:
   
   ```php
   class UserProfileType extends AbstractType
   {
       public function getBlockPrefix()
       {
           return 'profile';
       }
   }
   ```
   
   If you don't customize `getBlockPrefix()`, it defaults to the class name
   without "Type" suffix in underscore notation (here: "user_profile").
   
   If you want to create types that are compatible with Symfony 2.3 up to 2.8
   and don't trigger deprecation errors, implement *both* `getName()` and
   `getBlockPrefix()`:
   
   ```php
   class ProfileType extends AbstractType
   {
       public function getName()
       {
           return $this->getBlockPrefix();
       }
       
       public function getBlockPrefix()
       {
           return 'profile';
       }
   }
   ```
   
   If you define your form types in the Dependency Injection configuration, you
   should further remove the "alias" attribute:
   
   Before:
   
   ```xml
   <service id="my.type" class="Vendor\Type\MyType">
       <tag name="form.type" alias="mytype" />
   </service>
   ```
   
   After:
   
   ```xml
   <service id="my.type" class="Vendor\Type\MyType">
       <tag name="form.type" />
   </service>
   ```
   
   Type extension should return the fully-qualified class name of the extended
   type from `FormTypeExtensionInterface::getExtendedType()` now.
   
   Before:
   
   ```php
   class MyTypeExtension extends AbstractTypeExtension
   {
       public function getExtendedType()
       {
           return 'form';
       }
   }
   ```
   
   After:
   
   ```php
   use Symfony\Component\Form\Extension\Core\Type\FormType;
   
   class MyTypeExtension extends AbstractTypeExtension
   {
       public function getExtendedType()
       {
           return FormType::class;
       }
   }
   ```
   
   If your extension has to be compatible with Symfony 2.3-2.8, use the 
   following statement:
   
   ```php
   use Symfony\Component\Form\AbstractType;
   use Symfony\Component\Form\Extension\Core\Type\FormType;
   
   class MyTypeExtension extends AbstractTypeExtension
   {
       public function getExtendedType()
       {
           method_exists(AbstractType::class, 'getBlockPrefix') ? FormType::class : 'form';
       }
   }
   ```
   
 * Returning type instances from `FormTypeInterface::getParent()` is deprecated
   and will not be supported anymore in Symfony 3.0. Return the fully-qualified
   class name of the parent type class instead.
   
   Before:
   
   ```php
   class MyType
   {
       public function getParent()
       {
           return new ParentType();
       }
   }
   ```
   
   After:
   
   ```php
   class MyType
   {
       public function getParent()
       {
           return ParentType::class;
       }
   }
   ```
   
 * Passing type instances to `Form::add()`, `FormBuilder::add()` and the
   `FormFactory::create*()` methods is deprecated and will not be supported
   anymore in Symfony 3.0. Pass the fully-qualified class name of the type
   instead.
   
   Before:
   
   ```php
   $form = $this->createForm(new MyType());
   ```
   
   After:
   
   ```php
   $form = $this->createForm(MyType::class);
   ```

Translator
----------

 * The `getMessages()` method of the `Symfony\Component\Translation\Translator` was deprecated and will be removed in
   Symfony 3.0. You should use the `getCatalogue()` method of the `Symfony\Component\Translation\TranslatorBagInterface`.

   Before:

   ```php
   $messages = $translator->getMessages();
   ```

   After:

   ```php
    $catalogue = $translator->getCatalogue($locale);
    $messages = $catalogue->all();

    while ($catalogue = $catalogue->getFallbackCatalogue()) {
        $messages = array_replace_recursive($catalogue->all(), $messages);
    }
   ```

DependencyInjection
-------------------

 * The concept of scopes were deprecated, the deprecated methods are:

    - `Symfony\Component\DependencyInjection\ContainerBuilder::getScopes()`
    - `Symfony\Component\DependencyInjection\ContainerBuilder::getScopeChildren()`
    - `Symfony\Component\DependencyInjection\ContainerInterface::enterScope()`
    - `Symfony\Component\DependencyInjection\ContainerInterface::leaveScope()`
    - `Symfony\Component\DependencyInjection\ContainerInterface::addScope()`
    - `Symfony\Component\DependencyInjection\ContainerInterface::hasScope()`
    - `Symfony\Component\DependencyInjection\ContainerInterface::isScopeActive()`
    - `Symfony\Component\DependencyInjection\Definition::setScope()`
    - `Symfony\Component\DependencyInjection\Definition::getScope()`
    - `Symfony\Component\DependencyInjection\Reference::isStrict()`

  Also, the `$scope` and `$strict` parameters of `Symfony\Component\DependencyInjection\ContainerInterface::set()` and `Symfony\Component\DependencyInjection\Reference` respectively were deprecated.

 * A new `shared` flag has been added to the service definition
   in replacement of the `prototype` scope.

   Before:

   ```php
   use Symfony\Component\DependencyInjection\ContainerBuilder;

   $container = new ContainerBuilder();
   $container
       ->register('foo', 'stdClass')
       ->setScope(ContainerBuilder::SCOPE_PROTOTYPE)
   ;
   ```

   ```yml
   services:
       foo:
           class: stdClass
           scope: prototype
   ```

   ```xml
   <services>
       <service id="foo" class="stdClass" scope="prototype" />
   </services>
   ```

   After:

   ```php
   use Symfony\Component\DependencyInjection\ContainerBuilder;

   $container = new ContainerBuilder();
   $container
       ->register('foo', 'stdClass')
       ->setShared(false)
   ;
   ```

   ```yml
   services:
       foo:
           class: stdClass
           shared: false
   ```

   ```xml
   <services>
       <service id="foo" class="stdClass" shared="false" />
   </services>
   ```
