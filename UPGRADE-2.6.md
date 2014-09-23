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
