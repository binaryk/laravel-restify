<?php

namespace Binaryk\LaravelRestify\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;

class RestifyRegisterRequest extends FormRequest
{
    public function rules()
    {
        return [
            'email' => 'required|email|max:255|unique:'.Config::get('config.auth.table', 'users'),
            'password' => 'required|confirmed|min:6',
        ];
    }
}
