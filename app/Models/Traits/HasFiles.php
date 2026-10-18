<?php

namespace Core\Models\Traits;

use Closure;
use Core\Helpers\Helpers;
use Core\Models\BaseModel;
use Core\Models\File;
use Core\Models\Interfaces\HasFilesInterface;
use finfo;
use Illuminate\Database\DatabaseTransactionRecord;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\File as SymfonyFile;
use Symfony\Component\Mime\MimeTypes;
use Throwable;

/**
 * Trait HasFiles.
 *
 * For models, which files are attached to, which implement HasFilesInterface
 * with it. A file is stored on the files disk under the model's directory,
 * and recorded as a File, whose type, size and hash are taken from its
 * contents. A rollback of the transaction, in which a file is attached (or
 * of any transaction around it), deletes the stored file, and so does an
 * error while recording it. Deleting the model deletes its files, though a
 * soft-deleted model keeps them, until it is deleted permanently.
 *
 * @mixin BaseModel
 * @property Collection<int, File> $files
 * @see HasFilesInterface
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Models\Traits
 */
trait HasFiles
{
    /**
     * Boot the trait, registering its model event listeners.
     *
     * @return void
     */
    public static function bootHasFiles(): void
    {
        static::deleted(function (self $model) {
            if (method_exists($model, 'isForceDeleting') && !$model->isForceDeleting()) {
                return;
            }

            $model->files()->get()->each->delete();
        });
    }

    /**
     * Files attached to the model.
     *
     * @return MorphMany
     */
    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'fileable');
    }

    /**
     * Files attached to the model in the given collection.
     *
     * @param string $collection
     *
     * @return MorphMany
     */
    public function filesIn(string $collection): MorphMany
    {
        $files = $this->files();
        $files->where('collection', $collection);

        return $files;
    }

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
     * @throws LogicException When the model isn't saved.
     * @throws RuntimeException When the file can't be stored.
     * @throws Throwable
     */
    public function attachFile(
        SymfonyFile $file,
        ?string $name = null,
        string $collection = File::DEFAULT_COLLECTION,
        array $metadata = []
    ): File {
        $name ??= $file instanceof UploadedFile ? $file->getClientOriginalName() : $file->getFilename();
        $stream = fopen($file->getPathname(), 'rb');

        if ($stream === false) {
            throw new RuntimeException("The file [{$file->getPathname()}] couldn't be read.");
        }

        try {
            return $this->attach(
                $stream,
                $name,
                $collection,
                $file->getMimeType() ?? 'application/octet-stream',
                (int) $file->getSize(),
                (string) hash_file('sha256', $file->getPathname()),
                $metadata,
            );
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

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
     * @throws LogicException When the model isn't saved.
     * @throws RuntimeException When the file can't be stored.
     * @throws Throwable
     */
    public function attachContents(
        string $contents,
        string $name,
        string $collection = File::DEFAULT_COLLECTION,
        array $metadata = []
    ): File {
        return $this->attach(
            $contents,
            $name,
            $collection,
            (new finfo(FILEINFO_MIME_TYPE))->buffer($contents) ?: 'application/octet-stream',
            strlen($contents),
            hash('sha256', $contents),
            $metadata,
        );
    }

    /**
     * Get name of the disk, on which the model's files are stored.
     *
     * @return string
     */
    public function filesDisk(): string
    {
        return config('api-core.files.disk');
    }

    /**
     * Get the directory on the disk, in which the model's files are stored:
     * `{directory}/{morph}/{id}`, e.g. `files/models.users/1`.
     *
     * @return string
     */
    public function filesDirectory(): string
    {
        $parts = [trim((string) config('api-core.files.directory'), '/'), Helpers::morphOf($this), $this->getKey()];

        return implode('/', array_filter($parts, fn ($part) => $part !== ''));
    }

    /**
     * Store the given contents on the files disk, and record them as a file
     * of the model.
     *
     * @param string|resource $contents
     * @param string $name
     * @param string $collection
     * @param string $mimeType
     * @param int $size
     * @param string $sha256
     * @param array<string, mixed> $metadata
     *
     * @return File
     * @throws LogicException When the model isn't saved.
     * @throws RuntimeException When the file can't be stored.
     * @throws Throwable
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    protected function attach(
        mixed $contents,
        string $name,
        string $collection,
        string $mimeType,
        int $size,
        string $sha256,
        array $metadata
    ): File {
        if (!$this->exists) {
            throw new LogicException(static::class . ' must be saved, before files are attached to it.');
        }

        $diskName = $this->filesDisk();

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($diskName);

        $extension = MimeTypes::getDefault()->getExtensions($mimeType)[0] ?? null;
        $path = $this->filesDirectory() . '/' . Str::uuid() . ($extension ? '.' . $extension : '');

        if (!$disk->put($path, $contents)) {
            throw new RuntimeException("The file couldn't be stored at [$path] on the [$diskName] disk.");
        }

        try {
            /** @var File $file */
            $file = $this->files()->make([
                'name' => $name,
                'metadata' => empty($metadata) ? null : $metadata,
            ]);

            $file->forceFill([
                'collection' => $collection,
                'disk' => $diskName,
                'path' => $path,
                'mime_type' => $mimeType,
                'size' => $size,
                'sha256' => $sha256,
            ]);

            if (!$file->save()) {
                throw new RuntimeException("The file stored at [$path] couldn't be recorded.");
            }
        } catch (Throwable $exception) {
            $disk->delete($path);

            throw $exception;
        }

        $this->deleteStoredFileOnRollBack($file, fn () => $disk->delete($path));

        return $file->setRelation('fileable', $this);
    }

    /**
     * Delete the stored file, when the transaction, in which it is recorded,
     * or any transaction around it rolls back. The callback is registered on
     * each of them, as Laravel drops the rollback callbacks of a transaction,
     * which committed, when the one around it rolls back.
     *
     * @param File $file
     * @param Closure $delete
     *
     * @return void
     */
    protected function deleteStoredFileOnRollBack(File $file, Closure $delete): void
    {
        /** @var DatabaseTransactionsManager $transactions */
        $transactions = app('db.transactions');

        $transactions->callbackApplicableTransactions()
            ->where('connection', $file->getConnection()->getName())
            ->each(fn (DatabaseTransactionRecord $transaction) => $transaction->addCallbackForRollback($delete));
    }
}
