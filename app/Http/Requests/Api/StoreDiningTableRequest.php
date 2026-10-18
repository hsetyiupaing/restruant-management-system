<?php

namespace App\Http\Requests\Api;

use App\Enums\TableShape;
use App\Models\Restaurant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDiningTableRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Restaurant $restaurant */
        $restaurant = $this->route('restaurant');

        return [
            'label' => [
                'required',
                'string',
                'max:50',
                Rule::unique('dining_tables', 'label')->where('restaurant_id', $restaurant->id),
            ],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'min_capacity' => ['sometimes', 'integer', 'min:1', 'max:100', 'lte:capacity'],
            'pos_x' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'pos_y' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'width' => ['sometimes', 'integer', 'min:10', 'max:10000'],
            'height' => ['sometimes', 'integer', 'min:10', 'max:10000'],
            'shape' => ['sometimes', Rule::enum(TableShape::class)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
