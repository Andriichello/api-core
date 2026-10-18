<?php

namespace Core\Http\Controllers;

use Core\Http\Controllers\Traits\NestsUnderParent;
use Core\Http\Requests\Crud\IndexRequest;
use Core\Http\Requests\Crud\UpdateRequest;
use Core\Http\Requests\CrudRequest;
use Core\Http\Requests\Settings\UpdateSettingsRequest;
use Core\Http\Responses\ApiResponse;
use Core\Models\BaseModel;
use Core\Models\Interfaces\HasSettingsInterface;
use Core\Policies\CrudPolicy;
use Core\Repositories\SettingRepository;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Class SettingController.
 *
 * Handles the settings of a model (see HasSettings), under which its routes
 * nest them, e.g. `profiles/{profile}/settings`: it shows all of them, with
 * the defaults of those, which aren't stored, and updates the given ones.
 * Both nest them by their keys, e.g. `{"invoice": {"numbering": {"strategy":
 * "sequence"}}}`. A controller is made for each model, whose settings have
 * routes: it names the model's class and route parameter, and takes the
 * model's policy, which authorizes each request as an ability on the model
 * (see NestsUnderParent), so a user, who can view the model, can see its
 * settings, and a user, who can update it, can change them.
 *
 * `routes()` registers the routes of the controller's operations.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Controllers
 */
abstract class SettingController extends CrudController
{
    use NestsUnderParent;

    /**
     * Operations, which the controller handles,
     * mapped to the classes of their requests.
     *
     * @var array<string, class-string<CrudRequest>>
     */
    protected array $operations = [
        'index' => IndexRequest::class,
        'update' => UpdateSettingsRequest::class,
    ];

    /**
     * Class of the model, whose settings the controller handles.
     *
     * @var class-string<BaseModel&HasSettingsInterface>
     */
    protected string $settable;

    /**
     * SettingController constructor.
     *
     * @param CrudPolicy $policy The policy of the model, whose settings the controller handles.
     * @param SettingRepository $repository
     */
    public function __construct(CrudPolicy $policy, SettingRepository $repository)
    {
        parent::__construct($policy, $repository);
    }

    /**
     * Register the routes of the controller's operations under the given URI,
     * which has the model's route parameter, e.g. `profiles/{profile}/settings`.
     *
     * @param string $uri
     *
     * @return void
     */
    public static function routes(string $uri): void
    {
        static::registerRoutes($uri, [
            'index' => ['get', ''],
            'update' => ['patch', ''],
        ]);
    }

    /**
     * Handle the `index` operation: all settings of the model.
     *
     * @param IndexRequest $request
     *
     * @return ApiResponse
     */
    protected function handleIndex(IndexRequest $request): ApiResponse
    {
        return $this->asSettingsResponse($this->settableOf($request));
    }

    /**
     * Handle the `update` operation: update the settings, which the body
     * gives, and answer with all of them.
     *
     * @param UpdateRequest $request
     *
     * @return ApiResponse
     * @throws ValidationException
     */
    protected function handleUpdate(UpdateRequest $request): ApiResponse
    {
        $settable = $this->settableOf($request);
        $values = $request->post();

        if (empty($values)) {
            throw ValidationException::withMessages([
                'settings' => ['The request has no settings to update.'],
            ]);
        }

        $settable->updateSettings($values);

        return $this->asSettingsResponse($settable)
            ->setMessage('Updated');
    }

    /**
     * Get a response with all settings of the given model, nested by their
     * keys.
     *
     * @param BaseModel&HasSettingsInterface $settable
     *
     * @return ApiResponse
     */
    protected function asSettingsResponse(BaseModel&HasSettingsInterface $settable): ApiResponse
    {
        return ApiResponse::ok([
            'data' => (object) Arr::undot($settable->settingValues()),
        ]);
    }

    /**
     * Get class of the model, whose settings the controller handles.
     *
     * @return class-string<BaseModel&HasSettingsInterface>
     */
    protected function parentClass(): string
    {
        return $this->settable;
    }

    /**
     * Get the model, whose settings the request handles.
     *
     * @param CrudRequest $request
     *
     * @return BaseModel&HasSettingsInterface
     * @throws LogicException When the model can't have settings.
     */
    protected function settableOf(CrudRequest $request): BaseModel&HasSettingsInterface
    {
        $settable = $this->parentOf($request);

        if (!$settable instanceof HasSettingsInterface) {
            throw new LogicException($this->settable . ' must implement ' . HasSettingsInterface::class . '.');
        }

        return $settable;
    }
}
