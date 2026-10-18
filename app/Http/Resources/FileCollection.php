<?php

namespace Core\Http\Resources;

/**
 * Class FileCollection.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Resources
 */
class FileCollection extends BaseCollection
{
    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = FileResource::class;
}
