UPGRADE FROM 2.5 to 2.6
=======================

Known Backwards-Compatibility Breaks
------------------------------------

* If you use the `PdoSessionHandler`, the session table now has a different
  schema and must be modified. Look below for more details.

Form
----

 * The "empty_value" option in the types "choice", "date", "datetime" and "time"
   was deprecated and replaced by a new option "placeholder". You should use
   the option "placeholder" together with the view variables "placeholder" and
   "placeholder_in_choices" now.

   The option "empty_value" and the view variables "empty_value" and
   "empty_value_in_choices" will be removed in Symfony 3.0.

   Before:

   ```php
   $form->add('category', 'choice', array(
       'choices' => array('politics', 'media'),
       'empty_value' => 'Select a category...',
   ));
   ```

   After:

   ```php
   $form->add('category', 'choice', array(
       'choices' => array('politics', 'media'),
       'placeholder' => 'Select a category...',
   ));
   ```

   Before:

   ```
   {{ form.vars.empty_value }}

   {% if form.vars.empty_value_in_choices %}
       ...
   {% endif %}
   ```

   After:

   ```
   {{ form.vars.placeholder }}

   {% if form.vars.placeholder_in_choices %}
       ...
   {% endif %}
   ```
   
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

Validator
---------

 * The internal method `setConstraint()` was added to
   `Symfony\Component\Validator\Context\ExecutionContextInterface`. With
   this method, the context is informed about the constraint that is currently
   being validated.

   If you implement this interface, make sure to add the method to your
   implementation. The easiest solution is to just implement an empty method:

   ```php
   public function setConstraint(Constraint $constraint)
   {
   }
   ```

 * Prior to 2.6 `Symfony\Component\Validator\Constraints\ExpressionValidator`
   would not execute the Expression if it was attached to a property on an
   object and that property was set to `null` or an empty string.

   To emulate the old behaviour change your expression to something like
   this:

   ```
   value == null or (YOUR_EXPRESSION)
   ```

Security
--------

 * The `SecurityContextInterface` is marked as deprecated in favor of the
   `Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface` and
   `Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface`.
   ```
   isGranted  => AuthorizationCheckerInterface
   getToken   => TokenStorageInterface
   setToken   => TokenStorageInterface
   ```
   The Implementations have moved too, The `SecurityContext` is marked as
   deprecated and has been split to use the `AuthorizationCheckerInterface`
   and `TokenStorage`. This change is 100% Backwards Compatible as the SecurityContext
   delegates the methods.

 * The service `security.context` is deprecated along with the above change. Recommended
   to use instead:
   ```
   @security.authorization_checker => isGranted()
   @security.token_storage         => getToken()
   @security.token_storage         => setToken()
   ```

HttpFoundation
--------------

 * The `PdoSessionHandler` to store sessions in a database changed significantly.
   This introduced a **backwards-compatibility** break in the schema of the
   session table. The following changes must be made to your session table:

   - Add a new integer column called `sess_lifetime`. Assuming you have the
     default column and table names, in MySQL this would be:
       ALTER TABLE `session` ADD `sess_lifetime` INT NOT NULL ;
   - Change the data column (default: `sess_value`) to be a Blob type. In
     MySQL this would be:
      ALTER TABLE `session` CHANGE `sess_value` `session_value` BLOB NOT NULL;

   There is also an [issue](https://github.com/symfony/symfony/issues/12834)
   that affects Windows servers.

   A legacy class, `LegacyPdoSessionHandler` has been created to ease backwards-compatibility issues when upgrading.

   The changes to the `PdoSessionHandler` are:
   - By default, it now implements session locking to prevent loss of data by concurrent access to the same session.
     - It does so using a transaction between opening and closing a session. For this reason, it's not
       recommended to use the same database connection that you also use for your application logic.
       Otherwise you have to make sure to access your database after the session is closed and committed.
       Instead of passing an existing connection to the handler, you can now also pass a DSN string which
       will be used to lazy-connect when a session is started.
     - Since accessing a session now blocks when the same session is still open, it is best practice to
       save the session as soon as you don't need to write to it anymore. For example, read-only AJAX
       request to a session can save the session immediately after opening it to increase concurrency.
     - As alternative to transactional locking you can also use advisory locks which do not require a transaction.
       Additionally, you can also revert back to no locking in case you have custom logic to deal with race conditions
       like an optimistic concurrency control approach. The locking strategy can be chosen by passing the corresponding
       constant as `lock_mode` option, e.g. `new PdoSessionHandler($pdoOrDsn, array('lock_mode' => PdoSessionHandler::LOCK_NONE))`.
       For more information please read the class documentation.
   - The expected schema of the table changed.
     - Session data is binary text that can contain null bytes and thus should also be saved as-is in a
       binary column like BLOB. For this reason, the handler does not base64_encode the data anymore.
     - A new column to store the lifetime of a session is required. This allows to have different
       lifetimes per session configured via session.gc_maxlifetime ini setting.
     - You would need to migrate the table manually if you want to keep session information of your users.
     - You could use `PdoSessionHandler::createTable` to initialize a correctly defined table depending on
       the used database vendor.

OptionsResolver
---------------

 * The "array" type hint was removed from the `OptionsResolverInterface` methods
   `setRequired()`, `setAllowedValues()`, `addAllowedValues()`,
   `setAllowedTypes()` and `addAllowedTypes()`. You must remove the type hint
   from your implementations.

 * The interface `OptionsResolverInterface` was deprecated, since
   `OptionsResolver` instances are not supposed to be shared between classes.
   You should type hint against `OptionsResolver` instead.

   Before:

   ```php
   protected function configureOptions(OptionsResolverInterface $resolver)
   {
       // ...
   }
   ```

   After:

   ```php
   protected function configureOptions(OptionsResolver $resolver)
   {
       // ...
   }
   ```

 * `OptionsResolver::isRequired()` now returns `true` if a required option has
   a default value set. The new method `isMissing()` exhibits the old
   functionality of `isRequired()`.

   Before:

   ```php
   $resolver->setRequired(array('port'));

   $resolver->isRequired('port');
   // => true

   $resolver->setDefaults(array('port' => 25));

   $resolver->isRequired('port');
   // => false
   ```

   After:

   ```php
   $resolver->setRequired(array('port'));

   $resolver->isRequired('port');
   // => true
   $resolver->isMissing('port');
   // => true

   $resolver->setDefaults(array('port' => 25));

   $resolver->isRequired('port');
   // => true
   $resolver->isMissing('port');
   // => false
   ```

 * `OptionsResolver::replaceDefaults()` was deprecated. Use `clear()` and
   `setDefaults()` instead.

   Before:

   ```php
   $resolver->replaceDefaults(array(
       'port' => 25,
   ));
   ```

   After:

   ```php
   $resolver->clear();
   $resolver->setDefaults(array(
       'port' => 25,
   ));
   ```

 * `OptionsResolver::setOptional()` was deprecated. Use `setDefined()` instead.

   Before:

   ```php
   $resolver->setOptional(array('port'));
   ```

   After:

   ```php
   $resolver->setDefined('port');
   ```

 * `OptionsResolver::isKnown()` was deprecated. Use `isDefined()` instead.

   Before:

   ```php
   if ($resolver->isKnown('port')) {
       // ...
   }
   ```

   After:

   ```php
   if ($resolver->isDefined('port')) {
       // ...
   }
   ```

 * The methods `setAllowedValues()`, `addAllowedValues()`, `setAllowedTypes()`
   and `addAllowedTypes()` were changed to modify one option at a time instead
   of batch processing options. The old API exists for backwards compatibility,
   but will be removed in Symfony 3.0.

   Before:

   ```php
   $resolver->setAllowedValues(array(
       'method' => array('POST', 'GET'),
   ));
   ```

   After:

   ```php
   $resolver->setAllowedValues('method', array('POST', 'GET'));
   ```

 * The class `Options` was merged into `OptionsResolver`. If you instantiated
   this class manually, you should instantiate `OptionsResolver` now.
   `Options` is now a marker interface implemented by `OptionsResolver`.

   Before:

   ```php
   $options = new Options();
   ```

   After:

   ```php
   $resolver = new OptionsResolver();
   ```

 * Normalizers for defined but unset options are not executed anymore. If you
   want to have them executed, you should define a default value.

   Before:

   ```php
   $resolver->setOptional(array('port'));
   $resolver->setNormalizers(array(
       'port' => function ($options, $value) {
           // return normalized value
       }
   ));

   $options = $resolver->resolve($options);
   ```

   After:

   ```php
   $resolver->setDefault('port', null);
   $resolver->setNormalizer('port', function ($options, $value) {
       // return normalized value
   });

   $options = $resolver->resolve($options);
   ```

 * When undefined options are passed, `resolve()` now throws an
   `UndefinedOptionsException` instead of an `InvalidOptionsException`.
   `InvalidOptionsException` is only thrown when option values fail their
   validation constraints.

   Before:

   ```php
   $resolver->setDefaults(array(
       'transport' => 'smtp',
       'port' => 25,
   ));
   $resolver->setAllowedTypes(array(
       'port' => 'integer',
   ));

   // throws InvalidOptionsException
   $resolver->resolve(array('foo' => 'bar'));

   // throws InvalidOptionsException
   $resolver->resolve(array('port' => '25'));
   ```

   After:

   ```php
   $resolver->setDefaults(array(
       'transport' => 'smtp',
       'port' => 25,
   ));
   $resolver->setAllowedTypes(array(
       'port' => 'integer',
   ));

   // throws UndefinedOptionsException
   $resolver->resolve(array('foo' => 'bar'));

   // throws InvalidOptionsException
   $resolver->resolve(array('port' => '25'));
   ```

VarDumper and DebugBundle
-------------------------

The component and the bundle are new to Symfony 2.6. We encourage you
to enable the bundle in your `app/AppKernel.php` for the *dev* or *test*
environments. Just add this line before loading the `WebProfilerBundle`:

```php
$bundles[] = new Symfony\Bundle\DebugBundle\DebugBundle();
```

Then enjoy dumping variables by calling `dump($var)` anywhere in your PHP
and `{% dump var %}` or `{{ dump(var) }}` in Twig. Dumps are displayed
**in the web debug toolbar**.

Translation
-----------

With `LoggingTranslator`, a new translator class is introduced with Symfony
2.6. By default, the `@translator` service is referring to this class in the
debug environment.

If you have your own services that depend on the `@translator` service and expect
this service to be an instance of either
`Symfony\Component\Translation\Translator` or
`Symfony\Bundle\FrameworkBundle\Translation\Translator`, e.g. by type-hinting
for either of these classes, you will need to change that type hint. You can
use the `TranslatorInterface` to be on the safe side for future changes.

Before:

```php
use Symfony\Component\Translation\Translator;

class MyService {
    public function __construct(Translator $translator)
    {
        ...
    }
}
```

After:

```php
use Symfony\Component\Translation\TranslatorInterface;

class MyService {
    public function __construct(TranslatorInterface $translator)
    {
        ...
    }
}
```
