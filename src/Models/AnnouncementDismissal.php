<?php

namespace LaraSaaS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementDismissal extends Model
{
    protected $table = 'larasaas_announcement_dismissals';

    protected $guarded = ['id'];

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class, 'announcement_id');
    }

    /**
     * Points at whatever model the HOST project configured — never a
     * hardcoded App\Models\User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('larasaas.user_model', 'App\\Models\\User'), 'user_id');
    }
}
