# Contribution Guidelines

- Run component-specific tests using ./phpunit src/Symfony/Component/TheComponent — never run all components at once.
- Use TDD: write a failing test first, then implement the fix.
- Run the full component test suite afterward to ensure nothing is broken.
- During git rebase/merge, set GIT_EDITOR=true and run tests of patched components to validate.
- Use comments sparingly — only when they add real value, and don't reference issues in the code nor in tests.
- no em-dashes, no Co-Authored-By field, no credit of Claude or Anthropic anywhere
