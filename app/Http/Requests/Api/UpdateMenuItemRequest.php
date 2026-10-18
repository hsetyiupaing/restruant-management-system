<?php

namespace App\Http\Requests\Api;

use App\Enums\Allergen;
use App\Enums\MenuCourse;
use App\Models\Restaurant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMenuItemRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Restaurant $restaurant */
        $restaurant = $this->route('restaurant');

        return [
            'category_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('categories', 'id')->where('restaurant_id', $restaurant->id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'course' => ['sometimes', Rule::enum(MenuCourse::class)],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'ingredients' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'allergens' => ['sometimes', 'nullable', 'array'],
            'allergens.*' => ['string', Rule::enum(Allergen::class)],
            'price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:999999.99'],
            'image_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'is_available' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'customization_groups' => ['sometimes', 'array', 'max:20'],
            'customization_groups.*' => ['array:id,name,type,options'],
            'customization_groups.*.id' => ['required', 'string', 'max:80', 'distinct'],
            'customization_groups.*.name' => ['required', 'string', 'max:100'],
            'customization_groups.*.type' => ['required', Rule::in(['addons', 'radio', 'checkbox'])],
            'customization_groups.*.options' => ['required', 'array', 'min:1', 'max:30'],
            'customization_groups.*.options.*' => ['array:id,name,price'],
            'customization_groups.*.options.*.id' => ['required', 'string', 'max:80', 'distinct'],
            'customization_groups.*.options.*.name' => ['required', 'string', 'max:100'],
            'customization_groups.*.options.*.price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'dietary_tags' => ['sometimes', 'array'],
            'dietary_tags.*' => ['string', Rule::exists('dietary_tags', 'slug')],
        ];
    }
}
