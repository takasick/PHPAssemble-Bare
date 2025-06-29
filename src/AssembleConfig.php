<?php

declare(strict_types=1);

namespace PHPAssembleBare;

class AssembleConfig
{
    // Default values
    private const DEFAULT_OUTPUT_FILENAME = 'bundle.php';
    private const DEFAULT_ENTRYPOINT = '';
    private const DEFAULT_ENTRYPOINT_ARGS = '$argc, $argv';
    private const DEFAULT_BUNDLE_TITLE = 'Bundle Version';
    private const DEFAULT_SOURCE_FILES = [];
    private const DEFAULT_SOURCE_FILES_EXCLUDE = [];
    private const DEFAULT_KEEP_NAMESPACES = true;
    private const DEFAULT_SHEBANG_LINE = '';
    private const DEFAULT_STRICT_TYPES = true;
    private const DEFAULT_OUTPUT_FORMAT = 'phar';
    
    public string $output;
    public string $entrypoint;
    public string $entrypointArgs;
    public string $bundleTitle;
    public array $sourceFiles;
    public array $sourceFilesExclude;
    public bool $keepNamespaces;
    public string $shebangLine;
    public bool $strictTypes;
    public string $outputFormat;

    public function __construct(array $data)
    {
        $this->output = $data['output'] ?? self::DEFAULT_OUTPUT_FILENAME;
        $this->entrypoint = $data['entrypoint'] ?? self::DEFAULT_ENTRYPOINT;
        $this->entrypointArgs = $data['entrypoint_args'] ?? self::DEFAULT_ENTRYPOINT_ARGS;
        $this->bundleTitle = $data['bundle_title'] ?? self::DEFAULT_BUNDLE_TITLE;
        $this->sourceFiles = $data['source_files'] ?? self::DEFAULT_SOURCE_FILES;
        $this->sourceFilesExclude = $data['source_files_exclude'] ?? self::DEFAULT_SOURCE_FILES_EXCLUDE;
        $this->keepNamespaces = $data['keep_namespaces'] ?? self::DEFAULT_KEEP_NAMESPACES;
        $this->shebangLine = $data['shebang_line'] ?? self::DEFAULT_SHEBANG_LINE;
        $this->strictTypes = $data['strict_types'] ?? self::DEFAULT_STRICT_TYPES;
        $this->outputFormat = $data['output_format'] ?? self::DEFAULT_OUTPUT_FORMAT;
        
        $this->sourceFiles = self::expandWildcards($this->sourceFiles);
        $this->sourceFilesExclude = self::expandWildcards($this->sourceFilesExclude);
        
        $this->sourceFiles = self::applyExclusions($this->sourceFiles, $this->sourceFilesExclude);
    }
    
    public function getRelativePaths(string $sourceDir): array
    {
        return array_map(function($file) use ($sourceDir) {
            return str_replace($sourceDir . '/', '', $file);
        }, $this->getAbsolutePaths($sourceDir));
    }
    
    public function getAbsolutePaths(string $sourceDir): array
    {
        return array_map(function($file) use ($sourceDir) {
            return $sourceDir . '/' . $file;
        }, $this->sourceFiles);
    }
    
    // Static methods for accessing default values in help text
    public static function getDefaultOutputFilename(): string
    {
        return self::DEFAULT_OUTPUT_FILENAME;
    }
    
    public static function getDefaultEntrypoint(): string
    {
        return self::DEFAULT_ENTRYPOINT;
    }
    
    public static function getDefaultEntrypointArgs(): string
    {
        return self::DEFAULT_ENTRYPOINT_ARGS;
    }
    
    /**
     * Expand wildcard patterns in file paths
     *
     * @param array $patterns Array of file patterns
     * @return array Expanded file paths
     */
    private static function expandWildcards(array $patterns): array
    {
        $files = [];
        
        foreach ($patterns as $pattern) {
            if (strpos($pattern, '*') !== false) {
                $matches = glob($pattern);
                if ($matches) {
                    $files = array_merge($files, $matches);
                }
            } else {
                $files[] = $pattern;
            }
        }
        
        // Remove duplicates and return
        return array_unique($files);
    }
    
    /**
     * Apply file exclusions to the source file list
     *
     * @param array $sourceFiles Source files to filter
     * @param array $excludeFiles Files to exclude
     * @return array Filtered file list
     */
    private static function applyExclusions(array $sourceFiles, array $excludeFiles): array
    {
        if (empty($excludeFiles)) {
            return $sourceFiles;
        }
        
        // Convert exclude files to absolute paths for comparison
        $excludeSet = array_flip($excludeFiles);
        
        return array_filter($sourceFiles, function($file) use ($excludeSet) {
            return !isset($excludeSet[$file]);
        });
    }
    
    /**
     * Get bundle version from git or composer.json
     */
    public function getBundleVersion(): string
    {
        // Try to get version from git
        if (is_dir('.git')) {
            $gitHash = exec('git rev-parse --short HEAD 2>/dev/null');
            if ($gitHash) {
                $gitTag = exec('git describe --tags --exact-match HEAD 2>/dev/null');
                if ($gitTag) {
                    return $gitTag;
                }
                return "git-{$gitHash}";
            }
        }
        
        // Try to get version from composer.json
        if (file_exists('composer.json')) {
            $composerData = json_decode(file_get_contents('composer.json'), true);
            if (isset($composerData['version'])) {
                return $composerData['version'];
            }
        }
        
        // Fallback to build timestamp
        return 'build-' . date('Ymd-His');
    }
    
    /**
     * Get compression type from output format
     */
    public function getCompressionType(): string
    {
        switch ($this->outputFormat) {
            case 'phar-gz':
                return 'gzip';
            case 'phar-bz2':
                return 'bzip2';
            case 'phar':
            default:
                return 'none';
        }
    }
    
    /**
     * Check if this is a Phar format
     */
    public function isPharFormat(): bool
    {
        return in_array($this->outputFormat, ['phar', 'phar-gz', 'phar-bz2']);
    }
}