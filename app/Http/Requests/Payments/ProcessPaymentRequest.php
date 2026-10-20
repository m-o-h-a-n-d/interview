<?php

namespace App\Http\Requests\Payments;

use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rules\Enum;

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
            'payment_method' => ['required', 'string', new Enum(PaymentMethod::class)],
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:99999999.99'],
            'currency' => ['nullable', 'string', 'size:3'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
            'gateway_details' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_id.required' => 'The order ID is required for processing payment.',
            'order_id.exists' => 'The specified order does not exist in our records.',
            'payment_method.required' => 'A valid payment method is required.',
            'payment_method.Illuminate\Validation\Rules\Enum' => 'Invalid payment method. Supported methods: credit_card, paypal, stripe.',
            'amount.min' => 'Payment amount must be greater than zero.',
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
