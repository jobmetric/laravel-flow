<?php

namespace JobMetric\Flow\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use JobMetric\Flow\Enums\FlowStateTypeEnum;

/**
 * Class Flow
 *
 * Represents a versioned workflow definition for a given subject (model/domain).
 * It can be filtered by subject triple (type/scope/key), environment, channel,
 * and active time window. Related states, transitions and tasks are accessible
 * via Eloquent relations.
 *
 * @package JobMetric\Flow
 *
 * @property int $id The primary identifier of the flow row.
 * @property string $subject_type The class name of the related subject model.
 * @property string|null $subject_scope Optional scope discriminator (tenant/org/etc.).
 * @property int|null $subject_key Optional subject primary key to pin a specific entity.
 * @property int $version Version number of this flow definition.
 * @property bool $is_default Whether this flow is preferred among candidates.
 * @property bool $status Active flag (true=enabled, false=disabled).
 * @property Carbon|null $active_from Start of activity window (UTC).
 * @property Carbon|null $active_to End of activity window (UTC).
 * @property string|null $channel Optional channel key (e.g., web, api, pos).
 * @property int $ordering Relative priority among candidates (higher=preferred).
 * @property int|null $rollout_pct Optional canary percentage (0..100).
 * @property string|null $environment Deployment environment (e.g., prod, staging).
 * @property Carbon|null $deleted_at Soft delete timestamp.
 * @property Carbon $created_at The timestamp when this flow was created.
 * @property Carbon $updated_at The timestamp when this flow was last updated.
 *
 * @property-read FlowState[] $states
 * @property-read FlowTransition[] $transitions
 * @property-read FlowTask[] $tasks
 * @property-read FlowInstance[] $flowInstances
 * @property-read FlowUse[] $uses
 * @property-read FlowState|null $startState
 * @property-read FlowState|null $endState
 *
 * @method static Builder|Flow whereSubjectType(string $subject_type)
 * @method static Builder|Flow whereSubjectScope(?string $subject_scope)
 * @method static Builder|Flow whereSubjectKey(?int $subject_key)
 * @method static Builder|Flow whereVersion(int $version)
 * @method static Builder|Flow whereIsDefault(bool $is_default)
 * @method static Builder|Flow whereStatus(bool $status)
 * @method static Builder|Flow whereChannel(?string $channel)
 * @method static Builder|Flow whereEnvironment(?string $environment)
 * @method static Builder|Flow whereOrdering(int $ordering)
 * @method static Builder|Flow whereRolloutPct(?int $rollout_pct)
 */
class Flow extends Model
{
    use HasFactory,
        SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'subject_type',
        'subject_scope',
        'subject_key',
        'version',
        'is_default',
        'status',
        'active_from',
        'active_to',
        'channel',
        'ordering',
        'rollout_pct',
        'environment',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'subject_type' => 'string',
        'subject_scope' => 'string',
        'subject_key' => 'integer',
        'version' => 'integer',
        'is_default' => 'boolean',
        'status' => 'boolean',
        'active_from' => 'datetime',
        'active_to' => 'datetime',
        'channel' => 'string',
        'ordering' => 'integer',
        'rollout_pct' => 'integer',
        'environment' => 'string',
    ];

    /**
     * Override the table name using config.
     *
     * @return string
     */
    public function getTable(): string
    {
        return config('workflow.tables.flow', parent::getTable());
    }

    /**
     * Get states defined for this flow.
     *
     * @return HasMany
     */
    public function states(): HasMany
    {
        return $this->hasMany(FlowState::class, 'flow_id');
    }

    /**
     * Get transitions defined for this flow.
     *
     * @return HasMany
     */
    public function transitions(): HasMany
    {
        return $this->hasMany(FlowTransition::class, 'flow_id');
    }

    /**
     * Get tasks of this flow via transitions.
     *
     * @return HasManyThrough
     */
    public function tasks(): HasManyThrough
    {
        return $this->hasManyThrough(FlowTask::class, FlowTransition::class, 'flow_id', 'flow_transition_id', 'id', 'id');
    }

    /**
     * Get flow instances of this flow via transitions.
     *
     * @return HasManyThrough
     */
    public function flowInstances(): HasManyThrough
    {
        return $this->hasManyThrough(FlowInstance::class, FlowTransition::class, 'flow_id', 'flow_transition_id', 'id', 'id');
    }

    /**
     * Get flow uses of this flow.
     *
     * @return HasMany
     */
    public function uses(): HasMany
    {
        return $this->hasMany(FlowUse::class, 'flow_id');
    }

    /**
     * Start state of this flow (single).
     *
     * @return HasOne
     */
    public function startState(): HasOne
    {
        return $this->hasOne(FlowState::class, 'flow_id')->where('type', FlowStateTypeEnum::START());
    }

    /**
     * End state of this flow (single).
     *
     * @return HasOne
     */
    public function endState(): HasOne
    {
        return $this->hasOne(FlowState::class, 'flow_id')->where('type', FlowStateTypeEnum::END());
    }
}
