<?php

namespace App\Http\Requests;

use App\Models\Schedule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScheduleRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'scheduled_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'repeat_type' => ['nullable', 'string', 'max:255', Rule::in(['daily', 'weekly', 'monthly'])],
            'repeat_until' => ['exclude_without:repeat_type', 'required', 'date', 'after:scheduled_date', 'before_or_equal:' . now()->addMonths(6)]
        ];
    }
}
