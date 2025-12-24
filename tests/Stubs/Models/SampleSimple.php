<?php

namespace JobMetric\Flow\Tests\Stubs\Models;

use Illuminate\Database\Eloquent\Model;
use JobMetric\Flow\HasFlow;

class SampleSimple extends Model
{
    use HasFlow;

    protected $table = 'samples';

    protected $fillable = [];
}
