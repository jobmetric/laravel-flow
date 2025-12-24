<?php

namespace JobMetric\Flow\Support;

use Illuminate\Database\Eloquent\Model;
use JobMetric\Flow\HasWorkflow;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Finder\Finder;

/**
 * Locator responsible for discovering Eloquent models that use the HasWorkflow trait
 * by scanning the filesystem without relying on Composer's classmap or caching.
 */
class FilesystemHasWorkflowInModelLocator
{
    /**
     * Get all workflow-enabled model class names using a filesystem scan.
     *
     * @param array<int, string>|null $paths Directories to scan; if null, defaults will be used.
     *
     * @return array<int, string>
     */
    public static function all(?array $paths = null): array
    {
        $paths ??= static::defaultPaths();

        return static::scan($paths);
    }

    /**
     * Perform the actual filesystem scan and discover workflow-enabled models.
     *
     * @param array<int, string> $paths Directories to scan.
     *
     * @return array<int, string>
     */
    protected static function scan(array $paths): array
    {
        $finder = new Finder();

        $finder->files()->in($paths)->name('*.php');

        $result = [];

        foreach ($finder as $file) {
            $contents = $file->getContents();

            if (! str_contains($contents, 'HasWorkflow')) {
                continue;
            }

            if (! str_contains($contents, 'extends Model') &&
                ! str_contains($contents, 'extends \\Illuminate\\Database\\Eloquent\\Model') &&
                ! str_contains($contents, 'extends Illuminate\\Database\\Eloquent\\Model')) {
                continue;
            }

            [$namespace, $className] = static::extractClassFromSource($contents);

            if ($className === null) {
                continue;
            }

            $fqcn = $namespace ? $namespace . '\\' . $className : $className;

            if (! class_exists($fqcn)) {
                require_once $file->getRealPath();
            }

            if (! class_exists($fqcn)) {
                continue;
            }

            if (! is_subclass_of($fqcn, Model::class)) {
                continue;
            }

            try {
                $reflection = new ReflectionClass($fqcn);

                if ($reflection->isAbstract()) {
                    continue;
                }

                $traits = class_uses_recursive($fqcn);

                if (in_array(HasWorkflow::class, $traits, true)) {
                    $result[] = $fqcn;
                }
            } catch (ReflectionException $exception) {
                // Ignore classes that cannot be reflected.
            }
        }

        $result = array_values(array_unique($result));
        sort($result);

        return $result;
    }

    /**
     * Extract the namespace and class name from a PHP source string.
     *
     * This is a simple regex-based extractor that works for typical Laravel classes.
     *
     * @param string $source
     *
     * @return array{0: string|null, 1: string|null}
     */
    protected static function extractClassFromSource(string $source): array
    {
        $namespace = null;
        $className = null;

        if (preg_match('/namespace\s+([^;]+);/m', $source, $nsMatch)) {
            $namespace = trim($nsMatch[1]);
        }

        if (preg_match('/class\s+([^\s{]+)/m', $source, $classMatch)) {
            $className = trim($classMatch[1]);
        }

        return [$namespace, $className];
    }

    /**
     * Get default directories to scan for models.
     *
     * @return array<int, string>
     */
    protected static function defaultPaths(): array
    {
        return [
            base_path(),
        ];
    }
}
