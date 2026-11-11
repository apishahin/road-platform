<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class SmsGateway extends Model
{
    protected $fillable = [
        'name', 'channel', 'enabled', 'priority', 'method', 'url', 'body_type',
        'params', 'headers', 'sender_id', 'success_marker', 'api_key_enc',
        'last_status', 'last_note', 'last_used_at',
    ];

    protected $hidden = ['api_key_enc'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'priority' => 'integer',
            'last_used_at' => 'datetime',
        ];
    }

    public function setApiKey(?string $key): void
    {
        $this->api_key_enc = ($key === null || $key === '') ? null : Crypt::encryptString($key);
    }

    public function apiKey(): string
    {
        if (empty($this->api_key_enc)) {
            return '';
        }
        try {
            return Crypt::decryptString($this->api_key_enc);
        } catch (Throwable $e) {
            return '';
        }
    }

    public function hasKey(): bool
    {
        return !empty($this->api_key_enc);
    }
}