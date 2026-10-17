<?php

namespace App\Http\Requests\Api;

use App\Enums\TableShape;
use App\Models\DiningTable;
use App\Models\Restaurant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDiningTableRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Restaurant $restaurant */
        $restaurant = $this->route('restaurant');

        /** @var DiningTable $table */
        $table = $this->route('table');

        return [
            'label' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('dining_tables', 'label')
                    ->where('restaurant_id', $restaurant->id)
                    ->ignore($table->id),
            ],
            'capacity' => ['sometimes', 'required', 'integer', 'min:1', 'max:100'],
            'min_capacity' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'pos_x' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'pos_y' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'shape' => ['sometimes', Rule::enum(TableShape::class)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
