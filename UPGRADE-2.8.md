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
   $form = $this->createForm('form', $article, array('cascade_validation' => true))
       ->add('author', new AuthorType())
       ->getForm();
   ```
   
   After:
   
   ```php
   use Symfony\Component\Validator\Constraints\Valid;
   
   $form = $this->createForm('form', $article)
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

Config
------

 * The methods `setNormalizationClosures()` and `setFinalValidationClosures()` in
   `BaseNode` were deprecated, `setNormalizationCallbacks()` and
   `setFinalValidationCallbacks()` should be used instead.

 * The protected properties `normalizationClosures` and `finalValidationClosures` in
   `BaseNode` were renamed to `normalizationCallbacks` and `finalValidationCallbacks`.
