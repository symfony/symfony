UPGRADE FROM 2.5 to 2.6
=======================

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
