# PHPAssemble-Bare

PHPAssemble-Bare is a tool for bundling multiple PHP files into a single file.

## Features

Simply concatenates files in the order specified in the `source_files` configuration (you need to specify all files required for execution).
However, it uses nikic/php-parser to analyze the AST and removes `require`/`require_once` and `include`/`include_once` statements, and converts global scope `return` statements to `goto` statements.

## Installation

```bash
git clone <repository-url>
cd PHPAssemble-Bare
composer install
```

## Usage

### Basic Usage

```bash
# Direct execution
php bin/assemble.php --config=bundle.json

# Show help
php bin/assemble.php --help
```

### Configuration File (bundle.json)

```json
{
  "output": "dist/bundle.php",
  "entrypoint": "\\PHPAssembleBare\\PHPAssembleBare::main",
  "entrypoint_args": "$argc, $argv",
  "bundle_title": "My Bundle",
  "keep_namespaces": true,
  "strict_types": true,
  "shebang_line": "#!/usr/bin/env php",
  "source_files": [
    "src/*.php",
    "vendor/some-library/src/**/*.php"
  ],
  "source_files_exclude": [
    "src/test.php",
    "vendor/some-library/src/debug*.php"
  ]
}
```

#### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `output` | string | `bundle.php` | Output file path |
| `entrypoint` | string | `main` | Function/method to call when executing the bundle |
| `entrypoint_args` | string | `$argc, $argv` | Arguments to pass to the entry point |
| `bundle_title` | string | `Bundle Version` | Bundle title (for header comment) |
| `keep_namespaces` | boolean | `true` | Whether to preserve namespace declarations |
| `strict_types` | boolean | `true` | Whether to include `declare(strict_types=1)` |
| `shebang_line` | string | `""` | Shebang line for executable scripts |
| `source_files` | array | `[]` | Array of file patterns to bundle |
| `source_files_exclude` | array | `[]` | Array of file patterns to exclude from bundle |

### Wildcard Patterns

- `*` - Matches any characters except `/`
- `**` - Matches any characters including `/` (conceptually)
- `src/*.php` - All .php files in the src/ directory
- `src/*/*.php` - .php files in subdirectories of src/

### File Exclusion Feature

You can exclude specific files from the bundle using `source_files_exclude`.

```json
{
  "source_files": [
    "src/*.php",
    "vendor/library/**/*.php"
  ],
  "source_files_exclude": [
    "src/Test.php",
    "src/Debug.php",
    "vendor/library/debug/*.php"
  ]
}
```

- Exclusion patterns support wildcards just like `source_files`
- Exclusion processing is applied after `source_files` expansion
- Files are excluded by exact path matching

## Development

### Testing

```bash
composer test
```

### Build

```bash
composer run build
```

## License

See the LICENSE file for license information about this project.

## Dependencies

- **PHP**: 7.4 or higher
- **nikic/php-parser**: ^5.0

## Contributing

Please report bugs and feature requests through GitHub Issues.

---

[日本語版はこちら](README-ja.md)