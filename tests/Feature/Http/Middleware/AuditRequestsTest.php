<?php

namespace Core\Tests\Feature\Http\Middleware;

use Core\Http\Middleware\AuditRequests;
use Core\Models\AuditLog;
use Core\Models\Organization;
use Core\Models\User;
use Core\Repositories\AccessTokenRepository;
use Core\Tests\TestCase;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use LogicException;
use RuntimeException;

/**
 * Class AuditRequestsTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Feature\Http\Middleware
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class AuditRequestsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Signed-in user.
     *
     * @var User
     */
    private User $user;

    /**
     * Register the routes, which the middleware audits with different scopes, and the user of an organization.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', AuditRequests::ALIAS])->group(function () {
            Route::get('audited/{note}', fn () => ['ok' => true])->name('audited.show');
            Route::post('audited/{note}', fn () => ['ok' => true])->name('audited.store');
            Route::post('failing', fn (Request $request) => (function (string $password) {
                // Throws with the password among the arguments of the call, which the trace must leave out
                if ($password !== '') {
                    throw new RuntimeException('Broken', 0, new LogicException('Cause'));
                }
            })((string) $request->input('password')));
            Route::post('invalid', fn () => throw ValidationException::withMessages(['title' => 'Bad title.']));
            Route::post('excluded', fn () => ['ok' => true])->name('audited.excluded');
        });

        Route::middleware(['auth:sanctum', AuditRequests::ALIAS . ':data,reads'])->group(function () {
            Route::get('reads', fn () => ['ok' => true]);
            Route::post('data', fn () => ['ok' => true]);
        });

        $this->user = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);
    }

    /**
     * Get the only entry of the audit log.
     *
     * @return AuditLog
     */
    private function onlyEntry(): AuditLog
    {
        $this->assertSame(1, AuditLog::query()->count());

        return AuditLog::query()->firstOrFail();
    }

    /**
     * Test that a request is recorded with its method, route name, status, user and organization, and with the
     * HTTP side, but without its payload, by default, though the user signs in with Sanctum's fake token.
     *
     * @return void
     */
    public function testRecordsRequestWithHttpSide(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('audited/7?draft=1', ['title' => 'Note'])->assertOk();

        $entry = $this->onlyEntry();

        $this->assertSame(AuditLog::SOURCE_REQUEST, $entry->source);
        $this->assertSame('post', $entry->event);
        $this->assertSame('audited.store', $entry->name);
        $this->assertSame(200, $entry->status_code);
        $this->assertTrue($this->user->is($entry->causer));
        $this->assertSame($this->user->organization_id, $entry->organization_id);
        $this->assertNull($entry->subject_type);

        $http = $entry->metadata['http'];

        $this->assertSame('POST', $http['method']);
        $this->assertSame('/audited/7?draft=1', $http['url']);
        $this->assertSame(['note' => '7'], $http['parameters']);
        $this->assertSame(200, $http['status']);
        $this->assertSame('127.0.0.1', $http['ip']);
        $this->assertArrayNotHasKey('payload', $entry->metadata);
        $this->assertArrayNotHasKey('error', $entry->metadata);
    }

    /**
     * Test that a successful GET request is recorded only with the `reads` scope, while a failed one always is.
     *
     * @return void
     */
    public function testRecordsReadsOnlyWhenAskedOrFailed(): void
    {
        $this->actingAs($this->user, 'sanctum');

        $this->getJson('audited/7')->assertOk();
        $this->assertSame(0, AuditLog::query()->count());

        $this->getJson('reads')->assertOk();
        $this->assertSame('reads', $this->onlyEntry()->name);

        $this->getJson('missing')->assertNotFound();
        $this->getJson('audited/7?x_organization_id=1')->assertOk();
        $this->assertSame(1, AuditLog::query()->count());
    }

    /**
     * Test that the `data` scope records the payload with the scrub fields masked at any depth, and the uploaded
     * files described, but a too large payload is left out.
     *
     * @return void
     */
    public function testRecordsScrubbedPayload(): void
    {
        $this->actingAs($this->user, 'sanctum');

        $this->post('data', [
            'title' => 'Note',
            'Password' => 'secret-1',
            'nested' => ['refresh_token' => 'secret-2', 'kept' => 'value'],
            'scan' => UploadedFile::fake()->createWithContent('scan.pdf', 'PDF'),
        ])->assertOk();

        $this->assertSameIgnoringKeyOrder(
            [
                'title' => 'Note',
                'Password' => AuditRequests::MASK,
                'nested' => ['refresh_token' => AuditRequests::MASK, 'kept' => 'value'],
                'scan' => '[file: scan.pdf, 3 bytes]',
            ],
            $this->onlyEntry()->metadata['payload']
        );

        AuditLog::query()->toBase()->delete();
        config(['api-core.audit.max_payload_size' => 10]);

        $this->postJson('data', ['title' => str_repeat('a', 20)])->assertOk();

        $this->assertSame('[too large]', $this->onlyEntry()->metadata['payload']);
    }

    /**
     * Test that a server error is recorded with the response and the exception, whose trace has no arguments,
     * and the exceptions before it.
     *
     * @return void
     */
    public function testRecordsServerErrorWithException(): void
    {
        $this->actingAs($this->user, 'sanctum');

        // Traces keep the arguments of the calls, so the middleware has to leave them out
        $ignoreArgs = ini_set('zend.exception_ignore_args', '0');
        $maxLength = ini_set('zend.exception_string_param_max_len', '15');

        try {
            $this->postJson('failing', ['password' => 'secret-1'])->assertServerError();
        } finally {
            ini_set('zend.exception_ignore_args', (string) $ignoreArgs);
            ini_set('zend.exception_string_param_max_len', (string) $maxLength);
        }

        $error = $this->onlyEntry()->metadata['error'];

        $this->assertSame(RuntimeException::class, $error['exception']['class']);
        $this->assertSame('Broken', $error['exception']['message']);
        $this->assertSame('Cause', $error['exception']['previous'][0]['message']);
        $this->assertStringStartsWith('#0 ', $error['exception']['trace']);
        $this->assertStringNotContainsString('secret-1', $error['exception']['trace']);
        $this->assertIsString($error['response']);
    }

    /**
     * Test that a client error is recorded with the response and the exception, but without a trace.
     *
     * @return void
     */
    public function testRecordsClientErrorWithoutTrace(): void
    {
        $this->actingAs($this->user, 'sanctum');

        $this->postJson('invalid')->assertUnprocessable();

        $entry = $this->onlyEntry();

        $this->assertSame(422, $entry->status_code);
        $this->assertSame(ValidationException::class, $entry->metadata['error']['exception']['class']);
        $this->assertArrayNotHasKey('trace', $entry->metadata['error']['exception']);
        $this->assertStringContainsString('Bad title.', $entry->metadata['error']['response']);
    }

    /**
     * Test that a request, which authentication stops before the middleware, is recorded with the scopes, which
     * its route gives the middleware.
     *
     * @return void
     */
    public function testRecordsRequestStoppedBeforeTheMiddleware(): void
    {
        $this->postJson('data', ['title' => 'Note', 'password' => 'secret-1'])->assertUnauthorized();

        $entry = $this->onlyEntry();

        $this->assertSame(401, $entry->status_code);
        $this->assertNull($entry->causer_id);
        $this->assertSame(['title' => 'Note', 'password' => AuditRequests::MASK], $entry->metadata['payload']);
        $this->assertArrayNotHasKey('http', $entry->metadata);
    }

    /**
     * Test that the user, who switched to the account, is recorded.
     *
     * @return void
     */
    public function testRecordsUserWhoSwitchedToTheAccount(): void
    {
        $main = User::factory()->create();
        $token = app(AccessTokenRepository::class)->create($this->user, 'test', $main);

        $this->withToken($token->plainTextToken)->postJson('audited/7')->assertOk();

        $entry = $this->onlyEntry();

        $this->assertTrue($this->user->is($entry->causer));
        $this->assertSame($main->id, $entry->metadata['impersonator_id']);
    }

    /**
     * Test that excluded paths and route names aren't recorded.
     *
     * @return void
     */
    public function testSkipsExcludedRequests(): void
    {
        $this->actingAs($this->user, 'sanctum');

        config(['api-core.audit.except' => ['paths' => ['audited/*'], 'names' => ['audited.excluded']]]);

        $this->postJson('audited/7')->assertOk();
        $this->postJson('excluded')->assertOk();

        $this->assertSame(0, AuditLog::query()->count());
    }

    /**
     * Test that a failure to record is reported, rather than thrown.
     *
     * @return void
     */
    public function testReportsFailureToRecord(): void
    {
        Exceptions::fake();
        $this->actingAs($this->user, 'sanctum');

        Schema::drop('audit_logs');

        $this->postJson('audited/7')->assertOk();

        Exceptions::assertReported(QueryException::class);
    }
}
