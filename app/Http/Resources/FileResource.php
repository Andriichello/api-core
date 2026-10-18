<?php

namespace Core\Http\Resources;

use Core\Models\File;

/**
 * Class FileResource.
 *
 * A file without its disk and path, which may append `url`: a temporary URL,
 * which downloads it, or null, when its disk doesn't provide them.
 *
 * @property File $resource
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Resources
 */
class FileResource extends BaseResource
{
    /**
     * List of columns that are allowed as
     * appends on request.
     *
     * @var array<int|string, string>
     */
    protected array $allowedAppends = [
        'url',
    ];
}
