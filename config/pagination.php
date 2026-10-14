<?php

return [
    /*
     * The maximum number of results that will be returned
     * when using the paginator.
     */
    'max_results' => 1000,

    /*
     * The default number of results that will be returned
     * when using the paginator.
     */
    'default_size' => 100,

    /*
     * The key of the page[x] query string parameter for page number.
     */
    'number_parameter' => 'number',

    /*
     * The key of the page[x] query string parameter for page size.
     */
    'size_parameter' => 'size',

    /*
     * Simple pagination is used to perform a more efficient query.
     */
    'use_simple_pagination' => false,

    /*
     * Here you can override the base url to be used in the link items.
     */
    'base_url' => null,

    /*
     * The name of the query parameter used for pagination
     */
    'pagination_parameter' => 'page',
];
