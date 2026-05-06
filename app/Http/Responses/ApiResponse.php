<?php

namespace Core\Http\Responses;

use Illuminate\Http\JsonResponse;

/**
 * Class ApiResponse.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Responses
 */
class ApiResponse extends JsonResponse
{
    /**
     * Create a new instance of ApiResponse.
     *
     * @param array $data
     * @param int $status
     * @param string $message
     *
     * @return ApiResponse
     */
    public static function make(
        array $data,
        int $status,
        string $message
    ): ApiResponse {
        if (!isset($data['message'])) {
            $data['message'] = $message;
        }

        return new ApiResponse($data, $status);
    }

    /**
     * Set a message for the response.
     *
     * @param string $message
     *
     * @return ApiResponse
     */
    public function setMessage(string $message): ApiResponse
    {
        $data = $this->getData(true);
        $data['message'] = $message;

        $this->setData($data);

        return $this;
    }

    /**
     * Set a status code for the response.
     *
     * @param int $code
     *
     * @return ApiResponse
     */
    public function setStatus(int $code): ApiResponse
    {
        $this->setStatusCode($code);

        return $this;
    }

    /**
     * Create an instance of response with 200 as status code.
     *
     * @param array $data
     * @param string $msg
     *
     * @return ApiResponse
     * @SuppressWarnings(PHPMD)
     */
    public static function ok(array $data = [], string $msg = 'OK'): ApiResponse
    {
        return static::make($data, 200, $msg);
    }

    /**
     * Create an instance of response with 201 as status code.
     *
     * @param array $data
     * @param string $msg
     *
     * @return ApiResponse
     * @SuppressWarnings(PHPMD)
     */
    public static function created(array $data = [], string $msg = 'Created'): ApiResponse
    {
        return static::make($data, 201, $msg);
    }

    /**
     * Create an instance of response with 204 as status code.
     *
     * @param string $msg
     *
     * @return ApiResponse
     * @SuppressWarnings(PHPMD)
     */
    public static function noContent(string $msg = 'No Content'): ApiResponse
    {
        return static::make([], 204, $msg);
    }

    /**
     * Create an instance of response with 400 as status code.
     *
     * @param array $data
     * @param string $msg
     *
     * @return ApiResponse
     * @SuppressWarnings(PHPMD)
     */
    public static function bad(array $data = [], string $msg = 'Bad Request'): ApiResponse
    {
        return static::make($data, 400, $msg);
    }

    /**
     * Create an instance of response with 401 as status code.
     *
     * @param array $data
     * @param string $msg
     *
     * @return ApiResponse
     * @SuppressWarnings(PHPMD)
     */
    public static function unauthorized(array $data = [], string $msg = 'Unauthorized'): ApiResponse
    {
        return static::make($data, 401, $msg);
    }

    /**
     * Create an instance of response with 403 as status code.
     *
     * @param array $data
     * @param string $msg
     *
     * @return ApiResponse
     * @SuppressWarnings(PHPMD)
     */
    public static function forbidden(array $data = [], string $msg = 'Forbidden'): ApiResponse
    {
        return static::make($data, 403, $msg);
    }

    /**
     * Create an instance of response with 404 as status code.
     *
     * @param array $data
     * @param string $msg
     *
     * @return ApiResponse
     * @SuppressWarnings(PHPMD)
     */
    public static function notFound(array $data = [], string $msg = 'Not Found'): ApiResponse
    {
        return static::make($data, 404, $msg);
    }

    /**
     * Create an instance of response with 500 as status code.
     *
     * @param array $data
     * @param string $msg
     *
     * @return ApiResponse
     * @SuppressWarnings(PHPMD)
     */
    public static function error(array $data = [], string $msg = 'Internal server error'): ApiResponse
    {
        return static::make($data, 500, $msg);
    }
}
