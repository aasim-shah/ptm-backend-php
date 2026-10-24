<?php
namespace App\Services\Security;

use App\Models\User;
use App\Services\WorkspaceAccess;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AccountSecurity
{
    public function __construct(private Totp $totp) {}

    public function state(User $user)
    {
        return DB::table('account_security')->where('user_id', $user->id)->first();
    }

    public function required(User $user): bool
    {
        if ($this->state($user)?->enabled_at) { return true; }
        if (!config('security.require_admin_mfa')) { return false; }
        return $user->hasAnyRole(['Super Admin', 'Principal']) || app(WorkspaceAccess::class)->memberships($user)
            ->whereIn('role', ['school_admin', 'district_admin', 'sub_super_admin'])->exists();
    }

    public function begin(User $user, string $password): void
    {
        $this->password($user, $password);
        $secret = $this->totp->secret();
        DB::transaction(function () use ($user, $secret) {
            User::where('id', $user->id)->lockForUpdate()->first();
            if ($this->state($user)?->enabled_at) { throw ValidationException::withMessages(['code' => 'Authenticator is already enabled.']); }
            DB::table('account_security')->updateOrInsert(['user_id' => $user->id], ['secret' => Crypt::encryptString($secret),
                'pending_until' => now()->addMinutes(config('security.enrollment_minutes')), 'updated_at' => now(), 'created_at' => now()]);
        });
    }

    public function confirm(User $user, string $code): array
    {
        return DB::transaction(function () use ($user, $code) {
            $state = DB::table('account_security')->where('user_id', $user->id)->lockForUpdate()->first();
            if (!$state || $state->enabled_at || !$state->pending_until || now()->gte($state->pending_until)) { throw ValidationException::withMessages(['code' => 'Authenticator setup expired. Start again.']); }
            $counter = $this->totp->counter(Crypt::decryptString($state->secret), $code, now()->timestamp);
            if ($counter === null) { throw ValidationException::withMessages(['code' => 'The authenticator code is invalid.']); }
            $codes = array_map(fn() => bin2hex(random_bytes(10)), range(1, 8));
            DB::table('account_security')->where('user_id', $user->id)->update(['enabled_at' => now(), 'pending_until' => null, 'last_counter' => $counter,
                'recovery_hashes' => json_encode(array_map(fn($c) => Hash::make($c), $codes)), 'version' => $state->version + 1, 'updated_at' => now()]);
            app(WorkspaceAccess::class)->audit($user, null, 'security.mfa.enabled', $user->id);
            return $codes;
        });
    }

    public function verify(User $user, string $code): void
    {
        DB::transaction(function () use ($user, $code) {
            $state = DB::table('account_security')->where('user_id', $user->id)->lockForUpdate()->first();
            if (!$state?->enabled_at) { throw ValidationException::withMessages(['code' => 'Set up an authenticator first.']); }
            $counter = $this->totp->counter(Crypt::decryptString($state->secret), $code, now()->timestamp, $state->last_counter);
            $hashes = json_decode($state->recovery_hashes ?? '[]', true); $recovery = false;
            if ($counter === null && preg_match('/^[a-f0-9]{20}$/D', $code)) {
                foreach ($hashes as $key => $hash) {
                    if (Hash::check($code, $hash)) { unset($hashes[$key]); $recovery = true; break; }
                }
            }
            if ($counter === null && !$recovery) { throw ValidationException::withMessages(['code' => 'Code is invalid, expired or already used.']); }
            DB::table('account_security')->where('user_id', $user->id)->update(['last_counter' => $counter ?? $state->last_counter,
                'recovery_hashes' => json_encode(array_values($hashes)), 'updated_at' => now()]);
            app(WorkspaceAccess::class)->audit($user, null, $recovery ? 'security.mfa.recovery-used' : 'security.mfa.verified', $user->id);
        });
    }

    public function password(User $user, string $password): void
    {
        if (!Hash::check($password, $user->password)) { throw ValidationException::withMessages(['current_password' => 'Current password is incorrect.']); }
    }
}
