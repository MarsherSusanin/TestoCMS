<?php

namespace App\Modules\Auth\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class UserAccessRevocationService
{
    /** Call inside the same transaction that changes credentials or status. */
    public function revoke(User $user, ?string $keepSessionId = null): int
    {
        $user->increment('auth_version');
        $user->forceFill(['remember_token' => Str::random(60)])->save();
        $user->tokens()->delete();

        if (config('session.driver') !== 'database' || ! Schema::hasTable('sessions')) {
            return 0;
        }

        $sessions = DB::table('sessions')->where('user_id', $user->id);
        if ($keepSessionId !== null && $keepSessionId !== '') {
            $sessions->where('id', '!=', $keepSessionId);
        }

        return (int) $sessions->delete();
    }
}
