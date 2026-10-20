<?php

namespace App\Http\Requests\Orders;

use App\Enums\OrderStatus;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rules\Enum;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_name' => ['sometimes', 'nullable', 'string', 'min:2', 'max:255'],
            'customer_email' => ['sometimes', 'nullable', 'string', 'email', 'max:255'],
            'customer_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'status' => ['sometimes', 'nullable', new Enum(OrderStatus::class)],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'items' => ['sometimes', 'nullable', 'array', 'min:1'],
            'items.*.product_name' => ['required_with:items', 'string', 'min:1', 'max:255'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1', 'max:100000'],
            'items.*.unit_price' => ['required_with:items', 'numeric', 'min:0.01', 'max:99999999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_name.min' => 'Customer name must be at least 2 characters.',
            'customer_email.email' => 'Customer email must be a valid email address.',
            'status.Illuminate\Validation\Rules\Enum' => 'Invalid order status provided. Allowed statuses: pending, confirmed, cancelled.',
            'items.min' => 'When updating items, at least one item must be provided.',
            'items.*.product_name.required_with' => 'Product name is required for each item when updating items.',
            'items.*.quantity.min' => 'Item quantity must be at least 1.',
            'items.*.unit_price.min' => 'Item unit price must be at least 0.01.',
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
