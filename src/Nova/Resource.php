<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Nova;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
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

    // bez Policy Nova nie sprawdza szczegółu ani zapisu w API, a bool authorizedTo* działa tylko w UI
    public function authorizeToReplicate(Request $request): void
    {
        throw_unless($this->authorizedToReplicate($request), AuthorizationException::class);
    }

    public function authorizeToUpdate(Request $request): void
    {
        throw_unless($this->authorizedToUpdate($request), AuthorizationException::class);
    }

    public function authorizeToView(Request $request): void
    {
        throw_unless($this->authorizedToView($request), AuthorizationException::class);
    }

    public static function indexQuery(NovaRequest $request, $query): Builder
    {
        if (property_exists(static::class, 'channel')) {
            $query = $query->where('channel', static::$channel);
        }

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
