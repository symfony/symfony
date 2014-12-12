UPGRADE FROM 2.6 to 2.7
=======================

### Router

 * Route conditions now support container parameters which
   can be injected into condition using `%parameter%` notation.
   Due to the fact that it works by replacing all parameters
   with their corresponding values before passing condition
   expression for compilation there can be BC breaks where you
   could already have used percentage symbols. Single percentage symbol
   usage is not affected in any way. Conflicts may occur where
   you might have used `%` as a modulo operator, here's an example:
   `foo%bar%2` which would be compiled to `$foo % $bar % 2` in 2.6
   but in 2.7 you would get an error if `bar` parameter
   doesn't exist or unexpected result otherwise.
 
