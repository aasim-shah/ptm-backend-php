<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Insert-only record of call seconds charged to a user's subscription.
 */
class CallUsageLedger extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'call_usage_ledger';

    protected $fillable = ['user_id', 'user_call_room_id', 'channel_name', 'seconds', 'client_reported_seconds', 'source'];
}
