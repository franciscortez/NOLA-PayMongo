<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

class CheckoutSessionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation, sanitizing text inputs.
     */
    protected function prepareForValidation()
    {
        $data = $this->all();

        // If product details are present but missing price, default to total amount
        if (isset($data['product_details']) && is_array($data['product_details'])) {
            foreach ($data['product_details'] as &$product) {
                if (!isset($product['price']) && isset($data['amount'])) {
                    $product['price'] = $data['amount'];
                }
            }
        }

        // Recursively strip HTML tags from string inputs to prevent XSS.
        $this->merge($this->sanitizeData($data));
    }

    private function sanitizeData(array $data)
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->sanitizeData($value);
            } elseif (is_string($value)) {
                // Remove HTML tags and trim whitespace
                $data[$key] = trim(strip_tags($value));
            }
        }
        return $data;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => 'required|integer|min:100', // Minimum 1.00 PHP (100 centavos)
            'currency' => 'required|string|size:3|alpha',
            'description' => 'nullable|string|max:500',
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|array',
            'address.line1' => 'nullable|string|max:255',
            'address.line2' => 'nullable|string|max:255',
            'address.city' => 'nullable|string|max:255',
            'address.state' => 'nullable|string|max:255',
            'address.postal_code' => 'nullable|string|max:50',
            'address.country' => 'nullable|string|size:2|alpha',
            'product_details' => 'nullable|array',
            'product_details.*.name' => 'required_with:product_details|string|max:255',
            'product_details.*.price' => 'required_with:product_details|numeric|min:0',
            'product_details.*.qty' => 'required_with:product_details|integer|min:1',
            'publishable_key' => ['nullable', 'string', 'regex:/^pk_(test|live)_[a-zA-Z0-9]+$/'],
            'is_live_mode' => 'nullable|boolean',
            'transaction_id' => 'nullable|string|max:255',
            'order_id' => 'nullable|string|max:255',
            'invoice_id' => 'nullable|string|max:255',
            'location_id' => 'nullable|string|max:255',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        Log::error('CheckoutSessionRequest validation failed', [
            'errors' => $validator->errors()->toArray(),
            'payload' => $this->all(),
        ]);

        throw new HttpResponseException(response()->json([
            'error' => 'Validation error',
            'details' => $validator->errors(),
        ], 422));
    }
}
