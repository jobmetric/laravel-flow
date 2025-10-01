<?php

namespace JobMetric\Flow\Tests\Stubs\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use JobMetric\Flow\Tests\Stubs\Factories\UserFactory;

/**
 * @method static create(string[] $array)
 */
class User extends Model
{
    use HasFactory;

    protected $fillable = [
        'name'
    ];

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
