<?php

namespace App\Http\Requests\Api;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    /**
     * Staff record the outcome of a manual payment. The amount is capped at the
     * outstanding order balance by the controller, not trusted from input.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'status' => ['required', Rule::in([
                PaymentStatus::Paid->value,
                PaymentStatus::Failed->value,
            ])],
            'provider_ref' => ['nullable', 'string', 'max:255'],
        ];
    }
}
