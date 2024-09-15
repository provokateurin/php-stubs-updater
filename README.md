# PHP-Stubs-Generator

*Update your PHP stubs for Psalm and PHPStan.*

## Usage

1. Create a folder to store your stubs (e.g. `tests/stubs`)
2. Create empty files (inside your stubs folder) for the stubs you need following this pattern: `Some\Namespace\SomeClass` =>
   `some_namespace_someclass.php`.
3. Collect the list of folders that contain PHP files you need stubs for. Make sure to make this as specific as possible
   to reduce the time needed to update the stubs.
4. Run `update-stubs.php tests/stubs folder1 folder2 ...`
