<?php

declare(strict_types=1);

namespace PHPAssembleBare;

/**
 * Bundle PHP Script Builder
 */

class PHPAssembleBare {
    /**
     * Main entry point for the PHPAssembleBare CLI tool
     *
     * @param int $argc Number of command line arguments
     * @param array $argv Command line arguments
     * @return int Exit code
     */
    public static function main(int $argc, array $argv): int
    {
        $options = self::parseArguments($argv);
        
        if ($options['help']) {
            self::showUsage();
            return 0;
        }
        
        // Change working directory
        if ($options['working-dir'] !== null) {
            if (!is_dir($options['working-dir'])) {
                echo "Error: Working directory does not exist: {$options['working-dir']}\n";
                return 1;
            }
            if (!chdir($options['working-dir'])) {
                echo "Error: Failed to change to working directory: {$options['working-dir']}\n";
                return 1;
            }
            echo "Changed working directory to: " . getcwd() . "\n";
        }
        
        $config = self::loadConfig($options['config']);
        $builder = new BundleBuilder();
        
        if (!$builder->build($config)) {
            return 1;
        }
        
        return 0;
    }

    /**
     * Show usage information
     */
    public static function showUsage(): void
    {
        echo "Usage: " . basename(__FILE__) . " [options]\n";
        echo "\nOptions:\n";
        echo "  --config=PATH            JSON config file path (default: bundle.json)\n";
        echo "  --working-dir=PATH       Change working directory before building\n";
        echo "  --help                   Show this help message\n";
        echo "\nBundle Configuration (bundle.json):\n";
        echo "{\n";
        echo "  \"output\": \"string\",           // Output file path (default: " . BundleConfig::getDefaultOutputFilename() . ")\n";
        echo "  \"entrypoint\": \"string\",       // Entry point function/method (default: " . BundleConfig::getDefaultEntrypoint() . ")\n";
        echo "  \"entrypoint_args\": \"string\",  // Entry point arguments (default: " . BundleConfig::getDefaultEntrypointArgs() . ")\n";
        echo "  \"bundle_title\": \"string\",     // Bundle title for header comment\n";
        echo "  \"keep_namespaces\": boolean,   // Keep namespace declarations (default: true)\n";
        echo "  \"shebang_line\": \"string\",     // Shebang line for executable files (empty to disable)\n";
        echo "  \"strict_types\": boolean,      // Include declare(strict_types=1) (default: true)\n";
        echo "  \"source_files\": [            // Array of source files to bundle\n";
        echo "    \"path/to/file.php\",        //   Exact file path\n";
        echo "    \"src/*.php\",               //   Wildcard pattern\n";
        echo "    \"src/*/*.php\"              //   Nested wildcard pattern\n";
        echo "  ],\n";
        echo "  \"source_files_exclude\": [    // Array of files to exclude from bundle\n";
        echo "    \"src/test.php\",            //   Exact file path to exclude\n";
        echo "    \"src/debug*.php\"           //   Wildcard pattern to exclude\n";
        echo "  ]\n";
        echo "}\n";
        echo "\nWildcard patterns:\n";
        echo "  *                        Match any characters except /\n";
        echo "  **                       Match any characters including /\n";
        echo "  src/*.php                All .php files in src/\n";
        echo "  src/*/*.php              All .php files in src subdirectories\n";
    }

    /**
     * Parse command line arguments
     *
     * @param array $argv Command line arguments
     * @return array Parsed options
     */
    private static function parseArguments(array $argv): array
    {
        $options = [
            'config' => 'bundle.json',
            'working-dir' => null,
            'help' => false,
        ];
        
        for ($i = 1; $i < count($argv); $i++) {
            $arg = $argv[$i];
            
            if ($arg === '--help') {
                $options['help'] = true;
            } elseif (strpos($arg, '--config=') === 0) {
                $options['config'] = substr($arg, 9);
            } elseif (strpos($arg, '--working-dir=') === 0) {
                $options['working-dir'] = substr($arg, 14);
            } else {
                echo "Unknown option: {$arg}\n";
                exit(1);
            }
        }
        
        return $options;
    }

    /**
     * Load configuration from JSON file
     *
     * @param string $configPath Path to configuration file
     * @return BundleConfig Configuration object
     */
    private static function loadConfig(string $configPath): BundleConfig
    {
        if (!file_exists($configPath)) {
            echo "Config file not found: {$configPath}\n";
            exit(1);
        }
        
        $content = file_get_contents($configPath);
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "Invalid JSON in config file: " . json_last_error_msg() . "\n";
            exit(1);
        }
        
        return new BundleConfig($data);
    }
}