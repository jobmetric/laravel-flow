<?php

namespace JobMetric\Flow\Http\Requests\FlowTask;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use JobMetric\Flow\Contracts\AbstractTaskDriver;
use JobMetric\Flow\Facades\FlowTask as FlowTaskFacade;
use JobMetric\Flow\Models\FlowTask;
use JobMetric\Flow\Support\FlowTaskRegistry;
use JobMetric\Form\Http\Requests\FormBuilderRequest;

class UpdateFlowTaskRequest extends FormRequest
{
    /**
     * External context (injected via dto()).
     *
     * @var array<string,mixed>
     */
    protected array $context = [];

    /**
     * Set context from dto() helper.
     *
     * @param array<string,mixed> $context
     *
     * @return void
     */
    public function setContext(array $context): void
    {
        $this->context = $context;
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Build validation rules dynamically based on driver's form definition.
     *
     * @param array<string,mixed> $input
     * @param array<string,mixed> $context
     *
     * @return array<string,mixed>
     */
    public static function rulesFor(array $input, array $context = []): array
    {
        $flowTaskId = (int) ($context['flow_task_id'] ?? null);

        $rules = [
            'driver'   => [
                'sometimes',
                'string',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $class = trim(str_replace('/', '\\', (string) $value));

                    if (! class_exists($class)) {
                        $fail(trans('workflow::base.validation.flow_task.driver_not_exists'));

                        return;
                    }

                    if (! is_subclass_of($class, AbstractTaskDriver::class)) {
                        $fail(trans('workflow::base.validation.flow_task.driver_invalid'));

                        return;
                    }

                    // Resolve registry inside closure to ensure we get the current instance
                    /** @var FlowTaskRegistry $registry */
                    $registry = app('FlowTaskRegistry');

                    // Check if driver is registered in FlowTaskRegistry
                    if (! $registry->hasClass($class)) {
                        $fail(trans('workflow::base.validation.flow_task.driver_not_registered'));
                    }
                },
            ],
            'config'   => 'sometimes|array',
            'ordering' => 'sometimes|nullable|integer|min:0',
            'status'   => 'sometimes|boolean',
        ];

        // Get driver class from input or existing task
        $driverClass = $input['driver'] ?? null;

        if (is_null($driverClass) && $flowTaskId) {
            $task = FlowTask::query()->find($flowTaskId);
            if ($task && ! is_null($task->driver)) {
                $driverClass = is_object($task->driver) ? get_class($task->driver) : $task->driver;
            }
        }

        // Add dynamic config rules from driver's form definition
        if ($driverClass) {
            $driver = FlowTaskFacade::resolveDriver($driverClass);

            if (! is_null($driver)) {
                $formRequest = new FormBuilderRequest($driver->form());
                $formRules = $formRequest->rules();

                // Prefix each form rule with 'config.' and make them optional for update
                foreach ($formRules as $field => $fieldRules) {
                    $rules['config.' . $field] = $fieldRules;
                }
            }
        }

        return $rules;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return self::rulesFor($this->all(), $this->context);
    }

    /**
     * Hook to add cross-field validation after base rules pass.
     *
     * @param Validator $validator
     *
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            // Skip if base rules failed
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            // Additional validation can be added here if needed
        });
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [
            'driver'   => trans('workflow::base.fields.driver'),
            'config'   => trans('workflow::base.fields.config'),
            'ordering' => trans('workflow::base.fields.ordering'),
            'status'   => trans('workflow::base.fields.status'),
        ];

        // Get driver class from input or context
        $driverClass = $this->input('driver');

        if (is_null($driverClass) && isset($this->context['flow_task_id'])) {
            $task = FlowTask::query()->find($this->context['flow_task_id']);
            if ($task && ! is_null($task->driver)) {
                $driverClass = is_object($task->driver) ? get_class($task->driver) : $task->driver;
            }
        }

        if ($driverClass) {
            $driver = FlowTaskFacade::resolveDriver($driverClass);

            if (! is_null($driver)) {
                $formRequest = new FormBuilderRequest($driver->form());
                $formAttributes = $formRequest->attributes();

                // Prefix each form attribute with 'config.'
                foreach ($formAttributes as $field => $label) {
                    $attributes['config.' . $field] = $label;
                }
            }
        }

        return $attributes;
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [];

        // Get driver class from input or context
        $driverClass = $this->input('driver');

        if (is_null($driverClass) && isset($this->context['flow_task_id'])) {
            $task = FlowTask::query()->find($this->context['flow_task_id']);
            if ($task && ! is_null($task->driver)) {
                $driverClass = is_object($task->driver) ? get_class($task->driver) : $task->driver;
            }
        }

        if ($driverClass) {
            $driver = FlowTaskFacade::resolveDriver($driverClass);

            if (! is_null($driver)) {
                $formRequest = new FormBuilderRequest($driver->form());
                $formMessages = $formRequest->messages();

                // Prefix each form message key with 'config.'
                foreach ($formMessages as $key => $message) {
                    $messages['config.' . $key] = $message;
                }
            }
        }

        return $messages;
    }
}
