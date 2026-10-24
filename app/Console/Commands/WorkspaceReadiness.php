<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\ExecutableFinder;
class WorkspaceReadiness extends Command
{
    protected $signature='workspace:readiness {--production} {--json}';
    protected $description='Report release blockers without printing credentials or claiming provider acceptance';
    public function handle(): int
    {
        $checks=[];
        $add=function(string $name,bool $passed,string $detail) use (&$checks) { $checks[]=['check'=>$name,'passed'=>$passed,'detail'=>$detail]; };
        $add('php',PHP_VERSION_ID>=80200,'PHP 8.2 or newer is required.');
        $add('framework',version_compare(app()->version(),'12.0.0','>='),'The supported framework upgrade must be installed. Run composer audit in the release build.');
        if ($this->option('production')) {
            $add('environment',app()->environment('production'),'Production environment required for this check.');
            $add('debug',!config('app.debug'),'Debug mode must be disabled.');
            $add('https',str_starts_with((string)config('app.url'),'https://'),'APP_URL must be an HTTPS origin with valid TLS.');
            $add('session_cookies',(bool)config('session.secure') && config('session.http_only') && config('session.same_site')!=='none','Secure, HttpOnly, SameSite session cookies required.');
            $add('administrator_mfa',(bool)config('security.require_admin_mfa'),'Administrator MFA must be enforced.');
        }
        $add('application_key',strlen((string)config('app.key'))>=32,'Set a unique application encryption key and preserve it securely.');
        $add('smtp_tls',config('mail.mailers.smtp.verify_peer')!==false,'SMTP TLS verification must remain enabled.');
        $add('workspace_queue',config('queue.connections.workspace.driver')==='database','Dedicated persistent workspace queue required.');
        try {
            DB::select('SELECT 1');
            foreach (['workspace_memberships','account_security','workspace_jobs','workspace_files','workspace_runtime'] as $table) {
                $add('schema.'.$table,\Illuminate\Support\Facades\Schema::hasTable($table),'Run all versioned migrations.');
            }
            foreach (['worker','scheduler'] as $component) {
                $healthy=\Illuminate\Support\Facades\Schema::hasTable('workspace_runtime') && DB::table('workspace_runtime')->where('component',$component)->where('seen_at','>',now()->subMinutes(3))->exists();
                $add($component,$healthy,'A real background-process heartbeat newer than three minutes is required.');
            }
        } catch (\Throwable $e) { $add('database',false,'Database or migration check unavailable.'); }
        try {
            $key='health/'.bin2hex(random_bytes(12)); Storage::disk('private')->put($key,'check');
            $ok=Storage::disk('private')->get($key)==='check'; Storage::disk('private')->delete($key);
            $root=realpath(config('filesystems.disks.private.root'))?:config('filesystems.disks.private.root');
            $public=realpath(public_path())?:public_path();
            $add('private_storage',$ok && !str_starts_with($root,$public.DIRECTORY_SEPARATOR),'Private storage must be writable and outside public/.');
        } catch (\Throwable $e) { $add('private_storage',false,'Private storage is unavailable.'); }
        $binary=config('filesystems.scanner_binary','clamscan');
        $add('scanner',(bool)(new ExecutableFinder)->find($binary),'Install ClamAV and prove clean/infected/error scan paths in staging.');
        $definitions=glob('/var/lib/clamav/*.{cvd,cld}',GLOB_BRACE)?:[];
        $fresh=false; foreach ($definitions as $file) { if (filemtime($file)>time()-172800) { $fresh=true; } }
        $add('antimalware_definitions',$fresh,'ClamAV definitions must be present and recently refreshed.');
        $groups=[
            'agora'=>['AGORA_APP_ID','AGORA_APP_CERTIFICATE','AGORA_WEBHOOK_SECRET'],
            'stripe'=>['STRIPE_SECRET_KEY','STRIPE_WEBHOOK_SECRET'],
            'paypal'=>['PAYPAL_LIVE_ID','PAYPAL_LIVE_SECRET','PAYPAL_WEBHOOK_ID'],
        ];
        foreach ($groups as $group=>$keys) {
            $configured=true; foreach ($keys as $key) { $configured=$configured && (bool)config('environment.'.$key); }
            $add('provider.'.$group,$configured,'Required credentials must exist in secure environment configuration; presence does not verify integration.');
        }
        $add('email_enabled',(bool)config('delivery.email_enabled') && config('mail.default')==='smtp' && (bool)config('mail.mailers.smtp.host') && (bool)config('mail.mailers.smtp.password'),'Configure verified transactional SMTP delivery and enable workspace email.');
        $firebase=config('services.firebase.key_file');
        $add('push_enabled',(bool)config('delivery.push_enabled') && is_string($firebase) && is_readable($firebase) && is_file($firebase),'Configure FCM/APNs, a private readable service account, device lifecycle and workspace push.');
        foreach (['video'=>\App\Services\WorkspaceVideo::class,'billing'=>\App\Services\WorkspaceBilling::class,'minute_ledger'=>\App\Services\UsageLedger::class] as $name=>$class) {
            $add('module.'.$name,class_exists($class),'The organization-authorized module and its acceptance tests are required; legacy code presence is insufficient.');
        }
        $evidencePath=storage_path('app/release/acceptance.json'); $evidence=[];
        if (is_file($evidencePath)) { $evidence=json_decode(file_get_contents($evidencePath),true)?:[]; }
        foreach (['tenant_audit','provider_sandbox_journeys','backup_restore','mobile_acceptance','billing_policy','legal_privacy_review'] as $item) {
            $row=$evidence[$item]??[];
            $add('acceptance.'.$item,($row['status']??'')==='accepted' && !empty($row['owner']) && !empty($row['evidence']) && !empty($row['accepted_at']),
                'Record actual owner-reviewed acceptance evidence. Do not mark tests passed without performing them.');
        }
        $ready=!collect($checks)->contains('passed',false);
        if ($this->option('json')) { $this->line(json_encode(['ready'=>$ready,'checks'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)); }
        else { foreach ($checks as $check) { $this->line(($check['passed']?'PASS ':'BLOCKED ').$check['check'].' — '.$check['detail']); } $this->line($ready?'Release gates passed.':'Not ready for production. Resolve the reported blockers.'); }
        return $ready?0:1;
    }
}
