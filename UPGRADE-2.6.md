UPGRADE FROM 2.5 to 2.6
=======================

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
