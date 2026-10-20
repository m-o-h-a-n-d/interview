<?php

namespace App\Http\Requests\Payments;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ProcessPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'payment_method' => ['required', 'string'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'size:3'],
            'gateway_details' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_id.exists' => 'The specified order does not exist.',
            'payment_method.required' => 'A valid payment method (e.g. credit_card, paypal, stripe) is required.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation error.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
