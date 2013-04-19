CHANGELOG
=========

2.3.0
------

 * [BC BREAK] changed PropertyAccessor to continue its search for a property or
   method even if a non-public match was found. Before, a PropertyAccessDeniedException
   was thrown in this case. Class PropertyAccessDeniedException was removed
   now.
