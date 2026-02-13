<?php

namespace Movecloser\ProcessManager\Nova\Fields;

use Illuminate\Support\Arr;
use Laravel\Nova\Fields\Field;

/**
 * ExtraColumn field factory for Nova resources.
 *
 * Creates Nova fields that display values from the resource's meta.display array.
 * This allows dynamic field generation based on metadata stored in the process resource.
 */
class ExtraColumn
{
    /**
     * Create a new ExtraColumn instance.
     *
     * @param mixed $resource The Nova resource instance containing meta data.
     */
    public function __construct(private mixed $resource)
    {
    }

    /**
     * Create a Nova field that retrieves its value from the resource's meta.display array.
     *
     * @param string $fieldClass The fully qualified class name of the Nova field to instantiate.
     * @param string $name The display name of the field.
     * @param string $metaDisplayKey The key path within meta.display to retrieve the field value from.
     *
     * @return Field The instantiated Nova field with the resolved value.
     */
    public function make(
        string $fieldClass,
        string $name,
        string $metaDisplayKey,
    ): Field {
        return new $fieldClass($name, fn() => Arr::get($this->resource->meta, 'display.' . $metaDisplayKey) ?? '');
    }
}
