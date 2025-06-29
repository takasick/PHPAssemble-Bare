<?php

declare(strict_types=1);

namespace PHPAssembleBare;

use Phar;
use PharData;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use SplFileInfo;

class PharBuilder
{

    public function build(AssembleConfig $config): bool
    {
        $sourceDir = getcwd();
        $outputFile = $config->output;
        
        echo "Building Phar archive: {$outputFile}\n";
        
        // Validation checks
        if (!$this->validateEnvironment()) {
            return false;
        }
        
        // directory check
        $outputDir = dirname($outputFile);
        if (!is_dir($outputDir)) {
            echo "Error: can't find output directory: {$outputDir}\n";
            return false;
        }
        
        // Create temporary file for safe atomic write
        $tempFile = $outputFile . '.tmp.' . uniqid();
        
        // Ensure temp file is cleaned up on any failure
        $cleanupTemp = function() use ($tempFile) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        };
        
        // Add vendor files to source_files at the beginning
        if (is_dir($sourceDir . '/vendor')) {
            $vendorFiles = $this->findFiles($sourceDir, '/vendor');
            $config->sourceFiles = array_merge($vendorFiles, $config->sourceFiles ?? []);
        }
        
        try {
            // Create new Phar archive using temporary file
            $phar = new Phar($tempFile);
            $phar->startBuffering();
            
            // Set signature algorithm for better security
            $phar->setSignatureAlgorithm(Phar::SHA256);
            
            // Add files using the configured file list or auto-discovery
            $fileCount = $this->addFilesToPhar($phar, $config, $sourceDir);
            
            if ($fileCount === 0) {
                echo "Error: No files were added to the PHAR archive\n";
                $cleanupTemp();
                return false;
            }
            
            // Set custom stub
            $stub = $this->createStub($config);
            $phar->setStub($stub);
            
            // Set metadata
            $metadata = $this->createMetadata($config, $fileCount);
            $phar->setMetadata($metadata);
            
            $phar->stopBuffering();
            
            // Apply compression if specified
            $compression = $config->getCompressionType();
            if ($compression !== 'none') {
                $this->applyCompression($phar, $compression, $tempFile);
            }
            
            // Atomically move temp file to final location
            if (!rename($tempFile, $outputFile)) {
                echo "Error: Failed to move temporary PHAR file to output file: {$outputFile}\n";
                $cleanupTemp();
                return false;
            }
            
            // Make it executable if shebang is present
            if ($config->shebangLine !== '') {
                chmod($outputFile, 0755);
            }
            
            $this->showBuildSummary($outputFile, $fileCount);
            
            return true;
            
        } catch (\Exception $e) {
            echo "Error creating Phar archive: " . $e->getMessage() . "\n";
            $cleanupTemp();
            return false;
        }
    }
    
    private function validateEnvironment(): bool
    {
        // Check if Phar extension is enabled
        if (!extension_loaded('phar')) {
            echo "Error: Phar extension is not enabled\n";
            return false;
        }
        
        // Check if phar.readonly is disabled
        if (ini_get('phar.readonly')) {
            echo "Error: phar.readonly is enabled. To fix this:\n";
            echo "  1. Run with: php -d phar.readonly=0 bin/assemble --config=assemble-phar.json\n";
            echo "  2. Or set phar.readonly=0 in php.ini\n";
            return false;
        }
        
        return true;
    }
    
    private function addFilesToPhar(Phar $phar, AssembleConfig $config, string $sourceDir): int
    {
        $fileCount = 0;
        
        // Process all source_files (including vendor files added in build())
        if (!empty($config->sourceFiles)) {
            $files = $config->getAbsolutePaths($sourceDir);
            
            foreach ($files as $file) {
                if (!file_exists($file)) {
                    echo "Warning: File not found: {$file}\n";
                    continue;
                }
                
                $relativePath = str_replace($sourceDir . '/', '', $file);
                
                echo "Adding: {$relativePath}\n";
                $phar->addFile($file, $relativePath);
                $fileCount++;
            }
        }
        
        return $fileCount;
    }
    
    
    
    
    private function findFiles(string $sourceDir, string $subDir): array
    {
        $targetDir = $sourceDir . $subDir;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($targetDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        $files = [];
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            
            $relativePath = str_replace($sourceDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
            
            $files[] = $relativePath;
        }
        
        return $files;
    }
    
    private function createStub(AssembleConfig $config): string
    {
        $stub = '';
        
        // Add shebang if specified
        if ($config->shebangLine !== '') {
            $stub .= $config->shebangLine . "\n";
        }
        
        $stub .= "<?php\n";
        
        // Add strict types if enabled
        if ($config->strictTypes) {
            $stub .= "declare(strict_types=1);\n";
        }
        
        $stub .= "\n";
        $stub .= "/**\n";
        $stub .= " * " . $config->bundleTitle . "\n";
        $stub .= " * \n";
        $stub .= " * Generated: " . date('Y-m-d H:i:s T') . "\n";
        $stub .= " * Version: " . $config->getBundleVersion() . "\n";
        $stub .= " */\n";
        $stub .= "\n";
        $stub .= "Phar::mapPhar();\n";
        $stub .= "\n";
        
        // Use Composer's autoloader
        $stub .= "require_once 'phar://' . __FILE__ . '/vendor/autoload.php';\n";
        $stub .= "\n";
        
        // Call entrypoint if specified
        if ($config->entrypoint !== '') {
            $stub .= "exit(" . $config->entrypoint . "(" . $config->entrypointArgs . "));\n";
        }
        
        $stub .= "\n";
        $stub .= "__HALT_COMPILER();\n";
        
        return $stub;
    }
    
    private function createMetadata(AssembleConfig $config, int $fileCount): array
    {
        return [
            'title' => $config->bundleTitle,
            'generated' => date('Y-m-d H:i:s T'),
            'version' => $config->getBundleVersion(),
            'files' => $fileCount,
            'entrypoint' => $config->entrypoint,
            'compression' => $config->getCompressionType(),
            'tool' => 'PHPAssemble-Bare',
        ];
    }
    
    private function applyCompression(Phar $phar, string $compression, string $tempFile): void
    {
        switch (strtolower($compression)) {
            case 'gzip':
                if (extension_loaded('zlib')) {
                    try {
                        $phar->compressFiles(Phar::GZ);
                        echo "Applied GZIP compression\n";
                    } catch (\Exception $e) {
                        echo "Warning: GZIP compression failed: " . $e->getMessage() . "\n";
                    }
                } else {
                    echo "Warning: zlib extension not available, compression skipped\n";
                }
                break;
                
            case 'bzip2':
                if (extension_loaded('bz2')) {
                    try {
                        $phar->compressFiles(Phar::BZ2);
                        echo "Applied BZIP2 compression\n";
                    } catch (\Exception $e) {
                        echo "Warning: BZIP2 compression failed: " . $e->getMessage() . "\n";
                    }
                } else {
                    echo "Warning: bz2 extension not available, compression skipped\n";
                }
                break;
                
            case 'none':
                // No compression
                break;
                
            default:
                echo "Warning: Unknown compression format '{$compression}', compression skipped\n";
                break;
        }
    }
    
    private function showBuildSummary(string $outputFile, int $fileCount): void
    {
        $fileSize = filesize($outputFile);
        echo "PHAR archive built successfully!\n";
        echo "File: {$outputFile}\n";
        echo "Size: " . number_format($fileSize) . " bytes\n";
        echo "Files included: {$fileCount}\n";
    }
    
}