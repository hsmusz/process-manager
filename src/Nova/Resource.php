<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Nova;

use Illuminate\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource as NovaResource;

abstract class Resource extends NovaResource
{
    // !Important
    // All actions must use ->icon('') and are displayed as icons, not as dropdown
    // Use any of https://heroicons.com/

    /**
     * Default ordering for index query.
     *
     * @var array
     */
    public static array $sort = [
        'id' => 'asc',
    ];

    public static function indexQuery(NovaRequest $request, $query): Builder
    {
        if (empty($request->get('orderBy'))) {
            $query->getQuery()->orders = [];

            return $query->orderBy(key(static::$sort), reset(static::$sort));
        }

        return $query;
    }

    protected static function applySearch($query, $search): Builder
    {
        $search = addcslashes($search, '/');
        $search = addcslashes($search, '\\');

        return parent::applySearch($query, $search);
    }
}
