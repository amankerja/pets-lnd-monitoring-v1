<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManpowerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['lnd', 'admin', 'dept_admin', 'contractor_admin'], true);
    }

    public function rules(): array
    {
        $manpowerId = $this->route('manpower')?->id;

        return [
            'name' => ['required', 'string', 'max:150'],
            'nik' => [
                'required',
                'string',
                'max:50',
                Rule::unique('users', 'nik')->ignore($manpowerId),
            ],
            'email' => [
                'required',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($manpowerId),
            ],
            'role' => ['nullable', 'in:lnd,trainer,dept_admin,contractor_admin,peserta'],
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'job_position_id' => ['nullable', 'integer', 'exists:job_positions,id'],
            'password' => [$manpowerId ? 'nullable' : 'required', 'string', 'min:6'],
        ];
    }
}

