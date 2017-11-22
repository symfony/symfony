| Q             | A
| ------------- | ---
| Branch?       | master for features / 2.7 up to 4.0 for bug fixes <!-- see below -->
| Bug fix?      | yes/no
| New feature?  | yes/no <!-- don't forget to update src/**/CHANGELOG.md files -->
| BC breaks?    | yes/no
| Deprecations? | yes/no <!-- don't forget to update UPGRADE-*.md files -->
| Tests pass?   | yes/no
| Fixed tickets | #... <!-- #-prefixed issue number(s), if any -->
| License       | MIT
| Doc PR        | symfony/symfony-docs#... <!--highly recommended for new features-->

<!--
- Bug fixes must be submitted against the lowest branch where they apply
  (lowest branches are regularly merged to upper ones so they get the fixes too).
- Features and deprecations must be submitted against the master branch.
- Replace this comment by a description of what your PR is solving.
-->
