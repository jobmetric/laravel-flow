<?php

namespace JobMetric\Flow\Tests\Stubs\Models;

use Illuminate\Database\Eloquent\Model;
use JobMetric\Flow\HasWorkflow;

class Sample extends Model
{
    use HasWorkflow;

    protected $table = 'samples';

    protected $fillable = [];
}
