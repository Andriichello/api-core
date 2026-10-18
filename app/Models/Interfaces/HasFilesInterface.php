<?php

namespace Core\Models\Interfaces;

use Core\Models\File;
use Core\Models\Traits\HasFiles;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Symfony\Component\HttpFoundation\File\File as SymfonyFile;

/**
 * Interface HasFilesInterface.
 *
 * @see HasFiles
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Models\Interfaces
 */
interface HasFilesInterface
{
    /**
     * Files attached to the model.
     *
     * @return MorphMany
     */
    public function files(): MorphMany;

    /**
     * Files attached to the model in the given collection.
     *
     * @param string $collection
     *
     * @return MorphMany
     */
    public function filesIn(string $collection): MorphMany;

    /**
     * Attach the given file to the model, under its original name (an
     * uploaded file's client name), unless another one is given.
     *
     * @param SymfonyFile $file
     * @param string|null $name
     * @param string $collection
     * @param array<string, mixed> $metadata
     *
     * @return File
     */
    public function attachFile(
        SymfonyFile $file,
        ?string $name = null,
        string $collection = File::DEFAULT_COLLECTION,
        array $metadata = []
    ): File;

    /**
     * Attach a file with the given contents and name to the model, such as
     * a generated document.
     *
     * @param string $contents
     * @param string $name
     * @param string $collection
     * @param array<string, mixed> $metadata
     *
     * @return File
     */
    public function attachContents(
        string $contents,
        string $name,
        string $collection = File::DEFAULT_COLLECTION,
        array $metadata = []
    ): File;

    /**
     * Get name of the disk, on which the model's files are stored.
     *
     * @return string
     */
    public function filesDisk(): string;

    /**
     * Get the directory on the disk, in which the model's files are stored.
     *
     * @return string
     */
    public function filesDirectory(): string;
}
