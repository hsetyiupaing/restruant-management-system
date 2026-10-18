<?php

namespace App\Concerns;

use App\Enums\FoodRestriction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validation shared by registration, the API and the account settings page.
 *
 * The wire shape is one flat list of `{restriction, custom_label?}` entries,
 * which keeps the three kinds in a single normalised table while still letting
 * the form group them. An empty list is "none": no placeholder row is written.
 */
trait FoodRestrictionRules
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function foodRestrictionRules(): array
    {
        return [
            'food_restrictions' => ['sometimes', 'array', 'max:30'],
            'food_restrictions.*' => ['array'],
            'food_restrictions.*.restriction' => [
                'required',
                'string',
                Rule::enum(FoodRestriction::class),
            ],
            'food_restrictions.*.custom_label' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Reject duplicates, missing "Other" descriptions and labels attached to
     * fixed entries so the stored rows stay clean.
     *
     * The entries are passed in rather than read from `$this` because this is
     * used from both a FormRequest and a Fortify action.
     *
     * @param  mixed  $entries
     */
    protected function validateFoodRestrictions(Validator $validator, $entries): void
    {
        if (! is_array($entries)) {
            return;
        }

        $seen = [];

        foreach ($entries as $index => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $value = $entry['restriction'] ?? null;

            if (! is_string($value) || ($restriction = FoodRestriction::tryFrom($value)) === null) {
                continue;
            }

            if (isset($seen[$value])) {
                $validator->errors()->add(
                    "food_restrictions.{$index}.restriction",
                    'This restriction is already selected.',
                );

                continue;
            }

            $seen[$value] = true;

            $label = trim((string) ($entry['custom_label'] ?? ''));

            if ($restriction->requiresCustomLabel() && $label === '') {
                $validator->errors()->add(
                    "food_restrictions.{$index}.custom_label",
                    'Please describe this restriction.',
                );
            }

            if (! $restriction->requiresCustomLabel() && $label !== '') {
                $validator->errors()->add(
                    "food_restrictions.{$index}.custom_label",
                    'Only "Other" entries accept a custom description.',
                );
            }
        }
    }

    /**
     * Turn validated input into rows ready for upsert. The order is stable so
     * repeated saves do not churn timestamps unnecessarily.
     *
     * @param  array<int, array{restriction: string, custom_label?: string|null}>  $entries
     * @return array<int, array{restriction: string, kind: string, custom_label: string|null}>
     */
    public function foodRestrictionRows(array $entries): array
    {
        $rows = [];

        foreach ($entries as $entry) {
            $restriction = FoodRestriction::from($entry['restriction']);
            $label = $restriction->requiresCustomLabel()
                ? trim((string) ($entry['custom_label'] ?? ''))
                : null;

            $rows[$restriction->value] = [
                'restriction' => $restriction->value,
                'kind' => $restriction->kind()->value,
                'custom_label' => $label === '' ? null : $label,
            ];
        }

        return array_values($rows);
    }
}
