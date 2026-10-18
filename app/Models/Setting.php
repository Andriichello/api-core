<?php

namespace Core\Models;

use Core\Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Class Setting.
 *
 * The stored value of a setting of a model, which HasSettings defines,
 * validates and reads with its default.
 *
 * @property int $id
 * @property string $model_type
 * @property int $model_id
 * @property string $key
 * @property mixed $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property BaseModel $model
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Models
 */
#[UseFactory(SettingFactory::class)]
class Setting extends BaseModel
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory;

    /**
     * The attributes that are mass-assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'model_id' => 'int',
        'value' => 'json',
    ];

    /**
     * Model, whose setting it is.
     *
     * @return MorphTo
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
