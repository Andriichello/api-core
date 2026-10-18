<?php

namespace Core\Repositories;

use Core\Models\BaseModel;
use Core\Models\Setting;

/**
 * Class SettingRepository.
 *
 * Settings are read and updated with HasSettings (`setting()`,
 * `updateSettings()`), which validates them, rather than with the
 * repository's methods.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Repositories
 */
class SettingRepository extends CrudRepository
{
    /**
     * Repository's model class.
     *
     * @var BaseModel|string
     */
    protected BaseModel|string $model = Setting::class;
}
