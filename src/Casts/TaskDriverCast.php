<?php

namespace JobMetric\Flow\Casts;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use JobMetric\Flow\Contracts\AbstractTaskDriver;

/**
 * TaskDriverCast
 *
 * - set(): enforce strict validation on write (must be existing subclass of AbstractTaskDriver).
 * - get(): tolerate missing/invalid classes (return null and optionally log).
 * - serialize(): always expose stored FQCN for arrays/JSON, even if runtime value is null.
 */
class TaskDriverCast implements CastsAttributes
{
    /**
     * Convert stored FQCN to a concrete driver instance.
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value
     * @param array<string,mixed> $attributes
     *
     * @return AbstractTaskDriver|null
     * @throws BindingResolutionException
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?AbstractTaskDriver
    {
        if ($value === null || $value === '') {
            return null;
        }

        $class = trim(str_replace('/', '\\', (string) $value));

        if ($class === '') {
            return null;
        }

        if (! class_exists($class) || ! is_subclass_of($class, AbstractTaskDriver::class)) {
            Log::warning('Workflow task driver missing or invalid on retrieval.', [
                'stored_driver' => $class,
                'model'         => get_class($model),
                'task_id'       => $attributes['id'] ?? null,
                'transition_id' => $attributes['flow_transition_id'] ?? null,
            ]);

            return null;
        }

        /** @var AbstractTaskDriver $instance */
        $instance = app()->make($class);

        return $instance;
    }

    /**
     * Normalize and strictly validate the driver as FQCN on write.
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value
     * @param array<string,mixed> $attributes
     *
     * @return string|null
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_object($value)) {
            $value = get_class($value);
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException("Task driver value for [$key] must be a class name or instance.");
        }

        $class = trim(str_replace('/', '\\', $value));

        if (! class_exists($class)) {
            throw new InvalidArgumentException("Task driver class [$class] does not exist.");
        }

        if (! is_subclass_of($class, AbstractTaskDriver::class)) {
            throw new InvalidArgumentException("Task driver class [$class] must extend " . AbstractTaskDriver::class . ".");
        }

        return $class;
    }

    /**
     * Serialize as stored FQCN for arrays/JSON, even if runtime value is null.
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value
     * @param array<string,mixed> $attributes
     *
     * @return string|null
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            $raw = $attributes[$key] ?? null;

            if (is_string($raw) && $raw !== '') {
                $normalized = trim(str_replace('/', '\\', $raw));
                
                return $normalized === '' ? null : $normalized;
            }

            return null;
        }

        if (is_object($value)) {
            return get_class($value);
        }

        if (is_string($value)) {
            $normalized = trim(str_replace('/', '\\', $value));
            
            return $normalized === '' ? null : $normalized;
        }

        return null;
    }
}
