<?php

declare(strict_types=1);

namespace PHPAssembleBare;

use PhpParser\PhpVersion;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard as StandardPrettyPrinter;
use PhpParser\Node;
use PhpParser\Node\Stmt\{
    Declare_,
    Namespace_,
    Use_,
    Return_,
    If_,
    Function_,
    ClassMethod,
    Goto_
};
use PhpParser\Node\Expr\{
    Include_,
    Closure
};
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class BundleBuilder
{
    public function build(AssembleConfig $config): bool
    {
        $sourceDir = getcwd();
        $outputFile = $config->output;
        
        // Create temporary file for safe atomic write
        $tempFile = $outputFile . '.tmp.' . uniqid();
        
        // Ensure temp file is cleaned up on any failure
        $cleanupTemp = function() use ($tempFile) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        };
        
        // Build file paths from config
        $files = $config->getAbsolutePaths($sourceDir);
        
        echo "Building bundle script: {$outputFile}\n";
        
        // Generate metadata
        $buildTime = date('Y-m-d H:i:s T');
        $version = $config->getBundleVersion();
        $relativePaths = $config->getRelativePaths($sourceDir);
        
        // Initialize output file with header
        $header = '';
        if ($config->shebangLine !== '') {
            $header .= $config->shebangLine . "\n";
        }
        $header .= "<?php\n\n";
        if ($config->strictTypes) {
            $header .= "declare(strict_types=1);\n\n";
        }
        $header .= "/**\n";
        $header .= " * " . $config->bundleTitle . "\n";
        $header .= " * \n";
        $header .= " * Generated: {$buildTime}\n";
        $header .= " * Version: {$version}\n";
        $header .= " * \n";
        $header .= " * Bundled files:\n";
        foreach ($relativePaths as $path) {
            $header .= " *   - {$path}\n";
        }
        $header .= " */\n\n";
        
        // Write header to temporary file
        if (file_put_contents($tempFile, $header) === false) {
            echo "Error: Failed to write header to temporary file: {$tempFile}\n";
            $cleanupTemp();
            return false;
        }
        
        // Add each class file
        foreach ($files as $file) {
            if (!file_exists($file)) {
                echo "Error: File not found: {$file}\n";
                $cleanupTemp();
                return false;
            }
            
            $relativePath = str_replace($sourceDir . '/', '', $file);
            echo "Adding: {$relativePath}\n";
            
            // Build file content
            $fileContent = "// " . str_repeat('=', 77) . "\n";
            $fileContent .= "// " . $relativePath . "\n";
            $fileContent .= "// " . str_repeat('=', 77) . "\n";
            $fileContent .= $this->extractClassContent($file, $config) . "\n\n";
            
            // Append to temporary file
            if (file_put_contents($tempFile, $fileContent, FILE_APPEND | LOCK_EX) === false) {
                echo "Error: Failed to append file content to temporary file: {$tempFile}\n";
                $cleanupTemp();
                return false;
            }
        }
        
        // Add execution code at the end if entrypoint is specified
        if ($config->entrypoint !== '') {
            $executionCode = $this->getExecutionCode($config);
            if (file_put_contents($tempFile, $executionCode, FILE_APPEND | LOCK_EX) === false) {
                echo "Error: Failed to append execution code to temporary file: {$tempFile}\n";
                $cleanupTemp();
                return false;
            }
        }
        
        // Atomically move temporary file to final output file
        if (!rename($tempFile, $outputFile)) {
            echo "Error: Failed to move temporary file to output file: {$outputFile}\n";
            $cleanupTemp();
            return false;
        }
        
        // Make it executable if shebang is present
        if ($config->shebangLine !== '') {
            chmod($outputFile, 0755);
        }
        
        $fileSize = filesize($outputFile);
        echo "Bundle script built successfully!\n";
        echo "File: {$outputFile}\n";
        echo "Size: " . number_format($fileSize) . " bytes\n";
        
        return true;
    }
    
    
    private function extractClassContent(string $filePath, AssembleConfig $config): string
    {
        $content = file_get_contents($filePath);
        
        // Strip PHP tags and declare statement using PHP Parser
        $content = $this->stripPhpTags($content, $config, $filePath);
        
        // Remove empty lines at the beginning
        $content = ltrim($content, "\n\r");
        
        return $content;
    }
    
    private function stripPhpTags(string $content, AssembleConfig $config, string $filePath): string
    {
        $parserFactory = new ParserFactory();
        $parser = $parserFactory->createForNewestSupportedVersion();
        $stmts = $parser->parse($content);
        
        // Check if this file contains global return statements
        $hasGlobalReturn = $this->hasGlobalReturnStatements($stmts);
        
        $filteredStmts = [];
        foreach ($stmts as $stmt) {
            // Skip declare statements
            if ($stmt instanceof Declare_) {
                continue;
            }
            
            // Handle namespace statements based on configuration
            if ($stmt instanceof Namespace_) {
                if ($config->keepNamespaces) {
                    $filteredStmts[] = $stmt;
                } else {
                    // Extract statements inside namespace but skip the namespace itself
                    if ($stmt->stmts) {
                        foreach ($stmt->stmts as $namespacedStmt) {
                            if (!($namespacedStmt instanceof Use_)) {
                                $filteredStmts[] = $namespacedStmt;
                            }
                        }
                    }
                }
                continue;
            }
            
            // Handle use statements based on configuration
            if ($stmt instanceof Use_) {
                if ($config->keepNamespaces) {
                    $filteredStmts[] = $stmt;
                }
                continue;
            }
            
            
            $filteredStmts[] = $stmt;
        }
        
        $prettyPrinter = new StandardPrettyPrinter([
            'shortArraySyntax' => true,
        ]);
        
        // Clean AST nodes before printing
        $cleanedStmts = $this->cleanAst($filteredStmts);
        
        // Replace return statements with goto if file has global returns
        if ($hasGlobalReturn) {
            $labelName = $this->generateLabelName($filePath);
            $cleanedStmts = $this->replaceReturnsWithGoto($cleanedStmts, $labelName);
        }
        
        $result = $prettyPrinter->prettyPrint($cleanedStmts);
        
        // Add label at the end if this file had global return statements
        if ($hasGlobalReturn) {
            $labelName = $this->generateLabelName($filePath);
            $result .= "\n\n{$labelName}:";
        }
        
        return $result;
    }
    
    private function cleanAst(array $stmts): array
    {
        $nodeTraverser = new NodeTraverser();
        $nodeTraverser->addVisitor(new class extends NodeVisitorAbstract {
            public function enterNode(Node $node)
            {
                // Remove all comment attributes from nodes
                $node->setAttribute('comments', []);
                return $node;
            }
            
            public function leaveNode(Node $node)
            {
                // Remove require/include expressions from function bodies, methods, etc.
                if ($node instanceof Node\Stmt\Expression && 
                    $node->expr instanceof Include_) {
                    return NodeTraverser::REMOVE_NODE;
                }
                
                // Handle statements arrays (like function bodies)
                if (property_exists($node, 'stmts') && is_array($node->stmts)) {
                    $filteredStmts = [];
                    foreach ($node->stmts as $stmt) {
                        if (!($stmt instanceof Node\Stmt\Expression && 
                              $stmt->expr instanceof Include_)) {
                            $filteredStmts[] = $stmt;
                        }
                    }
                    $node->stmts = $filteredStmts;
                }
                
                return $node;
            }
        });
        
        return $nodeTraverser->traverse($stmts);
    }
    
    private function hasGlobalReturnStatements(array $stmts): bool
    {
        foreach ($stmts as $stmt) {
            if ($stmt instanceof Return_) {
                return true;
            }
            if ($stmt instanceof If_ && $this->containsReturnStatement($stmt)) {
                return true;
            }
            // Check namespace statements
            if ($stmt instanceof Namespace_ && $stmt->stmts) {
                if ($this->hasGlobalReturnStatements($stmt->stmts)) {
                    return true;
                }
            }
        }
        return false;
    }
    
    private function containsReturnStatement(Node $node): bool
    {
        if ($node instanceof Return_) {
            return true;
        }
        
        foreach ($node->getSubNodeNames() as $subNodeName) {
            $subNode = $node->$subNodeName;
            if (is_array($subNode)) {
                foreach ($subNode as $item) {
                    if ($item instanceof Node && $this->containsReturnStatement($item)) {
                        return true;
                    }
                }
            } elseif ($subNode instanceof Node && $this->containsReturnStatement($subNode)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function replaceReturnsWithGoto(array $stmts, string $labelName): array
    {
        $nodeTraverser = new NodeTraverser();
        $nodeTraverser->addVisitor(new class($labelName) extends NodeVisitorAbstract {
            private string $labelName;
            private int $depth = 0;
            
            public function __construct(string $labelName)
            {
                $this->labelName = $labelName;
            }
            
            public function enterNode(Node $node)
            {
                // Track when we enter function/method/closure scope
                if ($node instanceof Function_ || 
                    $node instanceof ClassMethod || 
                    $node instanceof Closure) {
                    $this->depth++;
                }
                
                return $node;
            }
            
            public function leaveNode(Node $node)
            {
                // Track when we leave function/method/closure scope
                if ($node instanceof Function_ || 
                    $node instanceof ClassMethod || 
                    $node instanceof Closure) {
                    $this->depth--;
                    return $node;
                }
                
                // Only replace return statements at global scope (depth = 0)
                if ($node instanceof Return_ && $this->depth === 0) {
                    return new Goto_($this->labelName);
                }
                
                return $node;
            }
        });
        
        return $nodeTraverser->traverse($stmts);
    }
    
    private function generateLabelName(string $filePath): string
    {
        // Generate a unique but short label using CRC32 hash of the file path
        $crc32 = crc32($filePath);
        $hexHash = strtoupper(dechex($crc32));
        
        return 'BNDL_' . $hexHash;
    }
    
    private function getExecutionCode(AssembleConfig $config): string
    {
        $code = "// " . str_repeat('=', 77) . "\n";
        $code .= "// Script Execution\n";
        $code .= "// " . str_repeat('=', 77) . "\n";
        $code .= $config->entrypoint . "(" . $config->entrypointArgs . ");\n";
        
        return $code;
    }
}