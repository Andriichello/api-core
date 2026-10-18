<?php

namespace Core\Models;

use Core\Database\Factories\FileFactory;
use Core\Queries\FileQuery;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Query\Builder as DatabaseBuilder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Class File.
 *
 * A file of a model (its `fileable`), which HasFiles attaches to it, kept on
 * a disk. Its name is the one, under which it was attached and is downloaded,
 * without slashes and control characters, while the path on the disk is
 * unique. Deleting the record deletes the stored file too, once the deletion
 * is committed.
 *
 * @property int $id
 * @property string $fileable_type
 * @property int $fileable_id
 * @property string $collection
 * @property string $name
 * @property string $disk
 * @property string $path
 * @property string $mime_type
 * @property int $size
 * @property string $sha256
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read string|null $url
 *
 * @property BaseModel $fileable
 *
 * @method static FileQuery query()
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Models
 */
#[UseFactory(FileFactory::class)]
class File extends BaseModel
{
    /** @use HasFactory<FileFactory> */
    use HasFactory;

    /**
     * Collection of a file, which is attached without one.
     *
     * @var string
     */
    public const string DEFAULT_COLLECTION = 'default';

    /**
     * The attributes that are mass-assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'metadata',
    ];

    /**
     * The attributes that should be hidden for serialization: where the file
     * is stored is an implementation detail.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'disk',
        'path',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'fileable_id' => 'int',
        'size' => 'int',
        'metadata' => 'array',
    ];

    /**
     * Perform any actions required after the model boots.
     *
     * @return void
     */
    protected static function booted(): void
    {
        // The stored file is kept, until the deletion of the record is committed
        static::deleted(function (File $file) {
            $file->getConnection()->afterCommit(fn () => $file->storage()->delete($file->path));
        });
    }

    /**
     * Model, to which the file is attached.
     *
     * @return MorphTo
     */
    public function fileable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Name of the file, without slashes, which would make it a path, and
     * control characters, which would break the headers of its download.
     *
     * @return Attribute
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => static::cleanName($value),
        );
    }

    /**
     * Temporary URL, which downloads the file, or null, when its disk doesn't
     * provide temporary URLs.
     *
     * @return Attribute
     */
    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->storage()->providesTemporaryUrls()
                ? $this->temporaryUrl(now()->addMinutes(config('api-core.files.url_lifetime')))
                : null,
        );
    }

    /**
     * Clean the given name of a file: slashes are replaced with dashes,
     * control characters are removed, and it is cut to 255 characters.
     *
     * @param string $name
     *
     * @return string
     */
    public static function cleanName(string $name): string
    {
        $name = trim((string) preg_replace(['/[\/\\\\]/', '/[\x00-\x1F\x7F]/'], ['-', ''], $name));

        return $name === '' ? 'file' : mb_substr($name, 0, 255);
    }

    /**
     * Get the disk, on which the file is stored.
     *
     * @return FilesystemAdapter
     */
    public function storage(): FilesystemAdapter
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($this->disk);

        return $disk;
    }

    /**
     * Get the contents of the stored file, or null, when it is missing.
     *
     * @return string|null
     */
    public function contents(): ?string
    {
        return $this->storage()->get($this->path);
    }

    /**
     * Determine if the stored file exists on its disk.
     *
     * @return bool
     */
    public function isStored(): bool
    {
        return $this->storage()->exists($this->path);
    }

    /**
     * Get a response, which downloads the file under its name.
     *
     * @return StreamedResponse
     */
    public function download(): StreamedResponse
    {
        return $this->storage()->download($this->path, $this->name, [
            'Content-Type' => $this->mime_type,
            'Content-Disposition' => $this->contentDisposition(),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Get a temporary URL, which downloads the file under its name, for disks,
     * which support them (`s3`, and `local` with its `serve` option). A local
     * disk's URL serves the file under its path, though.
     *
     * @param DateTimeInterface $expiresAt
     *
     * @return string
     */
    public function temporaryUrl(DateTimeInterface $expiresAt): string
    {
        return $this->storage()->temporaryUrl($this->path, $expiresAt, [
            'ResponseContentType' => $this->mime_type,
            'ResponseContentDisposition' => $this->contentDisposition(),
        ]);
    }

    /**
     * Get the Content-Disposition, which downloads the file under its name,
     * with the name in ASCII for clients, which don't read UTF-8 names. When
     * nothing of the name can be transliterated (such as emoji), the ASCII
     * name is `file` with the extension.
     *
     * @return string
     */
    public function contentDisposition(): string
    {
        $fallback = str_replace('%', '', Str::ascii($this->name));

        if (pathinfo($fallback, PATHINFO_FILENAME) === '') {
            $extension = pathinfo($fallback, PATHINFO_EXTENSION);
            $fallback = 'file' . ($extension === '' ? '' : ".$extension");
        }

        return HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $this->name, $fallback);
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param DatabaseBuilder $query
     *
     * @return FileQuery
     */
    public function newEloquentBuilder($query): FileQuery
    {
        return new FileQuery($query);
    }
}
