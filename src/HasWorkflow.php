<?php

namespace JobMetric\Flow;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use JobMetric\Flow\Models\Flow;
use JobMetric\Flow\Models\FlowUse;
use JobMetric\Flow\Support\FlowPicker;
use JobMetric\Flow\Support\FlowPickerBuilder;
use LogicException;
use UnitEnum;

/**
 * Trait HasWorkflow
 *
 * Binds a model instance to a single Flow via the flow_uses table.
 * Responsibilities:
 * - Build a per-model FlowPickerBuilder (overridable via buildFlowPicker()).
 * - Pick a Flow during "creating" and cache its id temporarily.
 * - Persist the FlowUse row on "created".
 * - Provide helpers to (re)bind, unbind, and eager-load the bound Flow.
 *
 * Usage:
 *   class Order extends Model {
 *       use HasWorkflow;
 *
 *       protected function buildFlowPicker(FlowPickerBuilder $builder): void
 *       {
 *           parent::buildFlowPicker($builder);
 *           // Customize here (subject scope, channel, environment, rollout key, etc.)
 *       }
 *   }
 */
trait HasWorkflow
{
    /**
     * Temporarily stores the selected flow id computed during creating event.
     *
     * @var int|null
     */
    protected ?int $selectedFlowIdForBinding = null;

    /**
     * Boots the HasWorkflow trait lifecycle hooks for creating/created events.
     *
     * @return void
     */
    public static function bootHasWorkflow(): void
    {
        static::creating(function (Model $model): void {
            /** @var self $model */
            $model->ensureHasStatusColumn();

            if (method_exists($model, 'flowUse') && $model->flowUse()->exists()) {
                return;
            }

            if (!property_exists($model, 'selectedFlowIdForBinding')) {
                $model->selectedFlowIdForBinding = null;
            }

            $flow = $model->pickFlow();
            $model->selectedFlowIdForBinding = $flow?->getKey();
        });

        static::created(function (Model $model): void {
            /** @var self $model */
            $model->ensureHasStatusColumn();

            if ($model->flowUse()->exists()) {
                return;
            }

            $flowId = $model->selectedFlowIdForBinding;

            if ($flowId === null) {
                $flow = $model->pickFlow();
                $flowId = $flow?->getKey();
            }

            if ($flowId === null) {
                return;
            }

            $model->flowUse()->create([
                'flow_id' => $flowId,
                'used_at' => Carbon::now('UTC'),
            ]);
        });
    }

    /**
     * Provides the one-to-one polymorphic binding from the model to Flow via flow_uses table.
     *
     * @return MorphOne<FlowUse>
     */
    public function flowUse(): MorphOne
    {
        return $this->morphOne(FlowUse::class, 'flowable');
    }

    /**
     * Returns the currently bound Flow model (convenience accessor, not a relation).
     * Prefer eager-loading via with(['flowUse.flow']) for performance.
     *
     * @return Flow|null
     */
    public function boundFlow(): ?Flow
    {
        $use = $this->relationLoaded('flowUse')
            ? $this->getRelation('flowUse')
            : $this->flowUse()->first();

        return $use?->flow()->first();
    }

    /**
     * Configures the FlowPickerBuilder for this model; can be overridden by subject models.
     *
     * @param FlowPickerBuilder $builder The builder to tune.
     *
     * @return void
     */
    protected function buildFlowPicker(FlowPickerBuilder $builder): void
    {
        $builder
            ->subjectType(static::class)
            ->subjectCollection($this->flowSubjectCollection())
            ->onlyActive(true)
            ->timeNow(Carbon::now('UTC'))
            ->orderByDefault()
            ->evaluateRollout(true)
            ->rolloutKeyResolver(function (Model $model): ?string {
                $key = $model->getKey();
                return $key === null ? null : (string)$key;
            });
    }

    /**
     * Resolves the logical subject collection; override in subject models if needed.
     *
     * @return string|null
     */
    protected function flowSubjectCollection(): ?string
    {
        $val = $this->getAttribute('collection');

        return $val === null ? null : (string)$val;
    }

    /**
     * Builds and returns a configured FlowPickerBuilder.
     *
     * @return FlowPickerBuilder
     */
    protected function makeFlowPicker(): FlowPickerBuilder
    {
        $builder = new FlowPickerBuilder();
        $this->buildFlowPicker($builder);

        return $builder;
    }

    /**
     * Picks a Flow using the configured builder.
     *
     * @return Flow|null
     */
    public function pickFlow(): ?Flow
    {
        return (new FlowPicker())->pick($this, $this->makeFlowPicker());
    }

    /**
     * Binds the given Flow to this model by upserting the FlowUse row.
     *
     * @param Flow $flow The flow to bind.
     * @param Carbon|null $usedAt Optional timestamp of binding.
     *
     * @return Model|MorphOne|FlowUse
     */
    public function bindFlow(Flow $flow, ?Carbon $usedAt = null): Model|MorphOne|FlowUse
    {
        $usedAt = $usedAt ?? Carbon::now('UTC');

        if ($this->relationLoaded('flowUse') && $this->getRelation('flowUse')) {
            /** @var FlowUse $use */
            $use = $this->getRelation('flowUse');
            $use->fill(['flow_id' => $flow->getKey(), 'used_at' => $usedAt])->save();

            return $use;
        }

        $existing = $this->flowUse()->first();
        if ($existing) {
            $existing->fill(['flow_id' => $flow->getKey(), 'used_at' => $usedAt])->save();

            return $existing;
        }

        return $this->flowUse()->create([
            'flow_id' => $flow->getKey(),
            'used_at' => $usedAt,
        ]);
    }

    /**
     * Re-picks a Flow and rebinds it if any is selected.
     *
     * @param callable(FlowPickerBuilder):void|null $tuner Optional builder mutator.
     *
     * @return Flow|null
     */
    public function rebindFlow(?callable $tuner = null): ?Flow
    {
        $builder = $this->makeFlowPicker();
        if ($tuner) {
            $tuner($builder);
        }

        $flow = (new FlowPicker())->pick($this, $builder);
        if ($flow) {
            $this->bindFlow($flow);
        }

        return $flow;
    }

    /**
     * Unbinds the Flow by deleting the FlowUse row if present.
     *
     * @return void
     */
    public function unbindFlow(): void
    {
        $this->flowUse()->delete();
        $this->selectedFlowIdForBinding = null;
    }

    /**
     * Query scope to eager-load the bound Flow efficiently.
     *
     * @param Builder $query The Eloquent query builder.
     *
     * @return Builder
     */
    public function scopeWithFlow(Builder $query): Builder
    {
        return $query->with(['flowUse.flow']);
    }

    /**
     * Provides the required status column name used by this trait; override per model if needed.
     *
     * @return string
     */
    protected function flowStatusColumn(): string
    {
        return 'status';
    }

    /**
     * Ensures that the subject model's table has the required status column.
     *
     * @return void
     */
    protected function ensureHasStatusColumn(): void
    {
        $column = $this->flowStatusColumn();
        $table = $this->getTable();

        if (!Schema::hasColumn($table, $column)) {
            throw new LogicException(sprintf('Model %s must have a "%s" column in table "%s" when using HasWorkflow.', static::class, $column, $table));
        }
    }

    /**
     * Detects the enum class used to cast the status column, if any.
     * It inspects Eloquent $casts and returns the class-string if it is a PHP enum, otherwise null.
     *
     * @return class-string<UnitEnum>|null
     */
    public function flowStatusEnumClass(): ?string
    {
        $column = $this->flowStatusColumn();
        $casts = method_exists($this, 'getCasts') ? $this->getCasts() : [];

        $cast = $casts[$column] ?? null;

        if (is_string($cast) && class_exists($cast) && is_subclass_of($cast, UnitEnum::class)) {
            /** @var class-string<UnitEnum> $cast */
            return $cast;
        }

        return null;
    }

    /**
     * Returns the allowed values of the status enum cast (if any).
     * Priority:
     *  1) If the enum class defines static values() (from your EnumMacros trait), call it.
     *  2) If it's a Backed Enum, return the scalar values from cases().
     *  3) Otherwise (pure enum), return the case names.
     *
     * @return array<int, string|int>|null
     */
    public function flowStatusEnumValues(): ?array
    {
        $enumClass = $this->flowStatusEnumClass();
        if ($enumClass === null) {
            return null;
        }

        if (method_exists($enumClass, 'values')) {
            /** @phpstan-ignore-next-line */
            return $enumClass::values();
        }

        $cases = $enumClass::cases();

        if (is_subclass_of($enumClass, BackedEnum::class)) {
            return array_map(static fn(BackedEnum $c) => $c->value, $cases);
        }

        return array_map(static fn(UnitEnum $c) => $c->name, $cases);
    }

    /**
     * get the current status value
     *
     * Returns the current status as a scalar:
     * - If the attribute is a Backed Enum, returns its scalar value.
     * - If the attribute is a pure Enum, returns its case name.
     * - Otherwise returns the raw attribute (string|int|null).
     *
     * @return string|int|null
     */
    public function flowCurrentStatusValue(): int|string|null
    {
        $column = $this->flowStatusColumn();

        $current = $this->getAttribute($column);

        if ($current instanceof UnitEnum) {
            if ($current instanceof BackedEnum) {
                return $current->value;
            }

            return $current->name;
        }

        return $current;
    }
}
