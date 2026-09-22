<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'batch_id'             => ['required', 'integer', 'exists:batches,id'],
            'adjustment_reason_id' => ['required', 'integer', 'exists:adjustment_reasons,id'],
            'new_quantity'         => ['required', 'integer', 'min:0'],
            'note'                 => ['nullable', 'string', 'max:1000'],
        ];
    }
}
