<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssueNotification extends Model
{
    protected $guarded = ['id'];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function contact(): BelongsTo
    {
        // A removed directory entry must not erase or break the historic notification log.
        return $this->belongsTo(AuthorityContact::class, 'authority_contact_id')->withTrashed();
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}