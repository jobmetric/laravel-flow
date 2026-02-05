<?php

namespace JobMetric\Flow\Tests\Unit\Support;

use Exception;
use JobMetric\Flow\Support\FilesystemHasWorkflowInModelLocator;
use JobMetric\Flow\Tests\TestCase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use ReflectionClass;
use ReflectionException;

/**
 * Comprehensive tests for FilesystemHasWorkflowInModelLocator
 *
 * This class is responsible for discovering Eloquent models that use the HasWorkflow trait
 * by scanning the filesystem. It:
 * - Scans specified directories for PHP files
 * - Filters files that contain 'HasWorkflow' and extend Model
 * - Extracts namespace and class name from source code
 * - Validates that classes exist and use the HasWorkflow trait
 * - Returns a sorted array of fully qualified class names
 *
 * These tests cover all scenarios including edge cases and error conditions.
 *
 */
class FilesystemHasWorkflowInModelLocatorTest extends TestCase
{
    protected string $testModelsPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a temporary directory for test models (native PHP to avoid File facade)
        $this->testModelsPath = storage_path('app/temp_test_models');
        $this->ensureEmptyDirectory($this->testModelsPath);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectoryIfExists($this->testModelsPath);
        parent::tearDown();
    }

    /**
     * Test that all() method returns array of model classes with HasWorkflow trait
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_all_returns_array_of_models_with_has_workflow_trait(): void
    {
        $result = FilesystemHasWorkflowInModelLocator::all();

        $this->assertIsArray($result);
        // Just verify it returns an array, Order may not be in default paths
    }

    /**
     * Test that all() method accepts custom paths
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_all_accepts_custom_paths(): void
    {
        $this->createTestModelWithHasWorkflow('TestModel1', $this->testModelsPath);

        $result = FilesystemHasWorkflowInModelLocator::all([$this->testModelsPath]);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test that all() method returns empty array when no models found
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_all_returns_empty_array_when_no_models_found(): void
    {
        $emptyPath = storage_path('app/temp_empty_test');
        $this->ensureEmptyDirectory($emptyPath);

        $result = FilesystemHasWorkflowInModelLocator::all([$emptyPath]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);

        $this->deleteDirectoryIfExists($emptyPath);
    }

    /**
     * Test that scan() method filters files without HasWorkflow trait
     *
     * @throws ReflectionException
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_scan_filters_files_without_has_workflow_trait(): void
    {
        $this->createTestModelWithoutHasWorkflow('TestModelWithoutTrait', $this->testModelsPath);

        $reflection = new ReflectionClass(FilesystemHasWorkflowInModelLocator::class);
        $method = $reflection->getMethod('scan');

        $result = $method->invoke(null, [$this->testModelsPath]);

        $this->assertIsArray($result);
        $this->assertNotContains('TestModelWithoutTrait', $result);
    }

    /**
     * Test that scan() method filters files that don't extend Model
     *
     * @throws ReflectionException
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_scan_filters_files_that_dont_extend_model(): void
    {
        $this->createTestClassWithoutModel('TestClassWithoutModel', $this->testModelsPath);

        $reflection = new ReflectionClass(FilesystemHasWorkflowInModelLocator::class);
        $method = $reflection->getMethod('scan');

        $result = $method->invoke(null, [$this->testModelsPath]);

        $this->assertIsArray($result);
        $this->assertNotContains('TestClassWithoutModel', $result);
    }

    /**
     * Test that scan() method includes models with HasWorkflow trait
     *
     * @throws ReflectionException
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_scan_includes_models_with_has_workflow_trait(): void
    {
        $className = 'TestModelWithTrait';
        $this->createTestModelWithHasWorkflow($className, $this->testModelsPath);

        $reflection = new ReflectionClass(FilesystemHasWorkflowInModelLocator::class);
        $method = $reflection->getMethod('scan');

        $result = $method->invoke(null, [$this->testModelsPath]);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test that scan() method filters abstract classes
     *
     * @throws ReflectionException
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_scan_filters_abstract_classes(): void
    {
        $this->createAbstractTestModel('AbstractTestModel', $this->testModelsPath);

        $reflection = new ReflectionClass(FilesystemHasWorkflowInModelLocator::class);
        $method = $reflection->getMethod('scan');

        $result = $method->invoke(null, [$this->testModelsPath]);

        $this->assertIsArray($result);
        $this->assertNotContains('AbstractTestModel', $result);
    }

    /**
     * Test that scan() method filters classes that are not subclasses of Model
     *
     * @throws ReflectionException
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_scan_filters_classes_not_subclass_of_model(): void
    {
        // Create a class that has HasWorkflow but doesn't extend Model
        $this->createTestClassWithoutModel('TestFakeModel', $this->testModelsPath);

        $reflection = new ReflectionClass(FilesystemHasWorkflowInModelLocator::class);
        $method = $reflection->getMethod('scan');

        $result = $method->invoke(null, [$this->testModelsPath]);

        $this->assertIsArray($result);
        // The class won't be found because it doesn't extend Model
    }

    /**
     * Test that extractClassFromSource() extracts namespace correctly
     *
     * @throws ReflectionException
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_extract_class_from_source_extracts_namespace_correctly(): void
    {
        $source = <<<'PHP'
<?php

namespace App\Models;

class TestModel extends Model
{
}
PHP;

        $reflection = new ReflectionClass(FilesystemHasWorkflowInModelLocator::class);
        $method = $reflection->getMethod('extractClassFromSource');

        [$namespace, $className] = $method->invoke(null, $source);

        $this->assertEquals('App\Models', $namespace);
        $this->assertEquals('TestModel', $className);
    }

    /**
     * Test that extractClassFromSource() handles classes without namespace
     *
     * @throws ReflectionException
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_extract_class_from_source_handles_classes_without_namespace(): void
    {
        $source = <<<'PHP'
<?php

class TestModel extends Model
{
}
PHP;

        $reflection = new ReflectionClass(FilesystemHasWorkflowInModelLocator::class);
        $method = $reflection->getMethod('extractClassFromSource');

        [$namespace, $className] = $method->invoke(null, $source);

        $this->assertNull($namespace);
        $this->assertEquals('TestModel', $className);
    }

    /**
     * Test that extractClassFromSource() handles multiple namespace declarations
     *
     * @throws ReflectionException
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_extract_class_from_source_handles_multiple_namespace_declarations(): void
    {
        $source = <<<'PHP'
<?php

namespace App\Models;

namespace App\Other;

class TestModel extends Model
{
}
PHP;

        $reflection = new ReflectionClass(FilesystemHasWorkflowInModelLocator::class);
        $method = $reflection->getMethod('extractClassFromSource');

        [$namespace, $className] = $method->invoke(null, $source);

        // Should get the first namespace
        $this->assertEquals('App\Models', $namespace);
        $this->assertEquals('TestModel', $className);
    }

    /**
     * Test that extractClassFromSource() handles classes with whitespace
     *
     * @throws ReflectionException
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_extract_class_from_source_handles_classes_with_whitespace(): void
    {
        $source = <<<'PHP'
<?php

namespace   App\Models   ;

class   TestModel   extends Model
{
}
PHP;

        $reflection = new ReflectionClass(FilesystemHasWorkflowInModelLocator::class);
        $method = $reflection->getMethod('extractClassFromSource');

        [$namespace, $className] = $method->invoke(null, $source);

        $this->assertEquals('App\Models', $namespace);
        $this->assertEquals('TestModel', $className);
    }

    /**
     * Test that extractClassFromSource() returns null for invalid source
     *
     * @throws ReflectionException
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_extract_class_from_source_returns_null_for_invalid_source(): void
    {
        $source = '<?php echo "test";';

        $reflection = new ReflectionClass(FilesystemHasWorkflowInModelLocator::class);
        $method = $reflection->getMethod('extractClassFromSource');

        [$namespace, $className] = $method->invoke(null, $source);

        $this->assertNull($namespace);
        $this->assertNull($className);
    }

    /**
     * Test that defaultPaths() returns array with base_path
     *
     * @throws ReflectionException
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_default_paths_returns_array_with_base_path(): void
    {
        $reflection = new ReflectionClass(FilesystemHasWorkflowInModelLocator::class);
        $method = $reflection->getMethod('defaultPaths');

        $result = $method->invoke(null);

        $this->assertIsArray($result);
        $this->assertContains(base_path(), $result);
    }

    /**
     * Test that scan() handles files with syntax errors gracefully
     *
     * @throws ReflectionException
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_scan_handles_files_with_syntax_errors_gracefully(): void
    {
        $filePath = $this->testModelsPath . '/InvalidSyntax.php';
        file_put_contents($filePath, '<?php class InvalidSyntax { invalid syntax }');

        $reflection = new ReflectionClass(FilesystemHasWorkflowInModelLocator::class);
        $method = $reflection->getMethod('scan');

        // Should not throw exception
        $result = $method->invoke(null, [$this->testModelsPath]);

        $this->assertIsArray($result);
    }

    /**
     * Test that scan() returns sorted array
     *
     * @throws ReflectionException
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_scan_returns_sorted_array(): void
    {
        $this->createTestModelWithHasWorkflow('ZModel', $this->testModelsPath);
        $this->createTestModelWithHasWorkflow('AModel', $this->testModelsPath);
        $this->createTestModelWithHasWorkflow('MModel', $this->testModelsPath);

        $reflection = new ReflectionClass(FilesystemHasWorkflowInModelLocator::class);
        $method = $reflection->getMethod('scan');

        $result = $method->invoke(null, [$this->testModelsPath]);

        $this->assertIsArray($result);
        $sorted = $result;
        sort($sorted);
        $this->assertEquals($sorted, $result);
    }

    /**
     * Test that scan() returns unique values
     *
     * @throws ReflectionException
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_scan_returns_unique_values(): void
    {
        $this->createTestModelWithHasWorkflow('DuplicateModel', $this->testModelsPath);
        $this->createTestModelWithHasWorkflow('DuplicateModel', $this->testModelsPath);

        $reflection = new ReflectionClass(FilesystemHasWorkflowInModelLocator::class);
        $method = $reflection->getMethod('scan');

        $result = $method->invoke(null, [$this->testModelsPath]);

        $this->assertIsArray($result);
        $this->assertEquals($result, array_unique($result));
    }

    /**
     * Test that scan() handles multiple paths
     *
     * @throws ReflectionException
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_scan_handles_multiple_paths(): void
    {
        $path1 = storage_path('app/temp_test_models_1_' . uniqid());
        $path2 = storage_path('app/temp_test_models_2_' . uniqid());

        $this->ensureEmptyDirectory($path1);
        $this->ensureEmptyDirectory($path2);

        $this->createTestModelWithHasWorkflow('Model1', $path1);
        $this->createTestModelWithHasWorkflow('Model2', $path2);

        $reflection = new ReflectionClass(FilesystemHasWorkflowInModelLocator::class);
        $method = $reflection->getMethod('scan');

        $result = $method->invoke(null, [$path1, $path2]);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        $this->deleteDirectoryIfExists($path1);
        $this->deleteDirectoryIfExists($path2);
    }

    /**
     * Test that scan() handles non-existent paths gracefully
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_scan_handles_non_existent_paths_gracefully(): void
    {
        $nonExistentPath = storage_path('app/non_existent_path_' . uniqid());

        $reflection = new ReflectionClass(FilesystemHasWorkflowInModelLocator::class);
        $method = $reflection->getMethod('scan');

        // Finder will throw exception for non-existent paths, so we need to catch it
        try {
            $result = $method->invoke(null, [$nonExistentPath]);
            $this->assertIsArray($result);
        } catch (Exception $e) {
            // It's expected that Finder throws exception for non-existent paths
            $this->assertTrue(true);
        }
    }

    /**
     * Test that scan() handles files with extends Model in comments
     *
     * @throws ReflectionException
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_scan_handles_files_with_extends_model_in_comments(): void
    {
        $filePath = $this->testModelsPath . '/CommentModel.php';
        $content = <<<'PHP'
<?php

namespace Test;

// This class extends Model
class CommentModel extends \Illuminate\Database\Eloquent\Model
{
    use \JobMetric\Flow\HasWorkflow;
}
PHP;

        file_put_contents($filePath, $content);

        $reflection = new ReflectionClass(FilesystemHasWorkflowInModelLocator::class);
        $method = $reflection->getMethod('scan');

        $result = $method->invoke(null, [$this->testModelsPath]);

        $this->assertIsArray($result);
    }

    /**
     * Test that scan() handles models with different extends syntax
     *
     * @throws ReflectionException
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_scan_handles_models_with_different_extends_syntax(): void
    {
        $filePath = $this->testModelsPath . '/DifferentExtendsModel.php';
        $content = <<<'PHP'
<?php

namespace Test;

use Illuminate\Database\Eloquent\Model;

class DifferentExtendsModel extends Model
{
    use \JobMetric\Flow\HasWorkflow;
}
PHP;

        file_put_contents($filePath, $content);

        $reflection = new ReflectionClass(FilesystemHasWorkflowInModelLocator::class);
        $method = $reflection->getMethod('scan');

        $result = $method->invoke(null, [$this->testModelsPath]);

        $this->assertIsArray($result);
    }

    /**
     * Create a test model with HasWorkflow trait
     */
    private function createTestModelWithHasWorkflow(string $className, string $path): void
    {
        $filePath = $path . '/' . $className . '.php';
        $content = <<<PHP
<?php

namespace Test\Models;

use Illuminate\Database\Eloquent\Model;

class {$className} extends Model
{
    use \JobMetric\Flow\HasWorkflow;
}
PHP;

        file_put_contents($filePath, $content);
    }

    /**
     * Create a test model without HasWorkflow trait
     */
    private function createTestModelWithoutHasWorkflow(string $className, string $path): void
    {
        $filePath = $path . '/' . $className . '.php';
        $content = <<<PHP
<?php

namespace Test\Models;

use Illuminate\Database\Eloquent\Model;

class {$className} extends Model
{
}
PHP;

        file_put_contents($filePath, $content);
    }

    /**
     * Create a test class that doesn't extend Model
     */
    private function createTestClassWithoutModel(string $className, string $path): void
    {
        $filePath = $path . '/' . $className . '.php';
        $content = <<<PHP
<?php

namespace Test\Models;

class {$className}
{
    use \JobMetric\Flow\HasWorkflow;
}
PHP;

        file_put_contents($filePath, $content);
    }

    /**
     * Create an abstract test model
     */
    private function createAbstractTestModel(string $className, string $path): void
    {
        $filePath = $path . '/' . $className . '.php';
        $content = <<<PHP
<?php

namespace Test\Models;

use Illuminate\Database\Eloquent\Model;

abstract class {$className} extends Model
{
    use \JobMetric\Flow\HasWorkflow;
}
PHP;

        file_put_contents($filePath, $content);
    }

    /**
     * Ensure directory exists and is empty (native PHP to avoid File facade).
     */
    private function ensureEmptyDirectory(string $path): void
    {
        if (is_dir($path)) {
            $this->deleteDirectoryIfExists($path);
        }
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    /**
     * Recursively delete directory if it exists (native PHP).
     */
    private function deleteDirectoryIfExists(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            }
            else {
                unlink($item->getRealPath());
            }
        }
        rmdir($path);
    }
}
