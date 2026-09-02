<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateAccountProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $emailChanged = Str::lower((string) $this->input('email')) !== Str::lower((string) $this->user()?->email);

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class, 'email')->ignore($this->user()?->getKey()),
            ],
            'profile_current_password' => [
                Rule::requiredIf($emailChanged),
                'nullable',
                'string',
                'current_password:web',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'profile_current_password.required' => 'Enter your current password before changing your email address.',
            'profile_current_password.current_password' => 'The current password you entered is not correct.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => Str::lower(trim((string) $this->input('email'))),
        ]);
    }
}
