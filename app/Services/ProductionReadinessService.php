<?php

namespace App\Services;

use App\Models\ProductionReadinessReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProductionReadinessService
{
    public function snapshotPayload(): array
    {
        $checks = [];
        $checks['Production environment selected'] = app()->environment('production');
        $checks['Debug output disabled'] = ! config('app.debug');
        $checks['Application URL uses HTTPS'] = str_starts_with(config('app.url', ''), 'https://');
        $checks['Secure HTTP-only session cookies enabled'] = (bool) config('session.secure') && (bool) config('session.http_only');
        $checks['Real email transport configured'] = ! in_array(config('mail.default'), ['log', 'array', 'failover', null], true);
        try {
            DB::connection()->getPdo();
            $checks['Database reachable'] = true;
        } catch (Throwable) {
            $checks['Database reachable'] = false;
        }
        $probe = 'readiness/'.Str::uuid().'.txt';
        try {
            $checks['Private storage writable'] = Storage::disk('local')->put($probe, 'readiness') && Storage::disk('local')->get($probe) === 'readiness';
        } catch (Throwable) {
            $checks['Private storage writable'] = false;
        } finally {
            try {
                Storage::disk('local')->delete($probe);
            } catch (Throwable) {
            }
        }
        try {
            $checks['Demo identities removed'] = ! User::where('email', 'like', '%@mdsiss.test')->exists();
            $administrators = User::where('status', 'active')->whereHas('roles', fn ($q) => $q->where('slug', 'system-super-administrator'))->get();
            $checks['Active administrator provisioned'] = $administrators->isNotEmpty();
            $checks['Privileged accounts use non-default passwords'] = ! $administrators->contains(fn ($u) => Hash::check('password', $u->password));
        } catch (Throwable) {
            $checks['Demo identities removed'] = false;
            $checks['Active administrator provisioned'] = false;
            $checks['Privileged accounts use non-default passwords'] = false;
        }
        try {
            $audit = Storage::disk('local')->exists('security/dependency-audit.json') ? json_decode(Storage::disk('local')->get('security/dependency-audit.json'), true) : null;
        } catch (Throwable) {
            $audit = null;
        }
        $checks['Dependency audit clean and current (24 hours)'] = is_array($audit) && ($audit['clean'] ?? false) && ($audit['lock_hash'] ?? '') === hash_file('sha256', base_path('composer.lock')) && is_numeric($audit['checked_at'] ?? null) && $audit['checked_at'] <= time() && $audit['checked_at'] >= time() - 86400;
        $verifiedAt = strtotime((string) config('mdsiss.backup_restore_verified_at'));
        $checks['Backup restore verified within 30 days'] = $verifiedAt && $verifiedAt <= time() && $verifiedAt >= time() - 30 * 86400 && filled(config('mdsiss.backup_restore_evidence'));
        $passed = array_keys(array_filter($checks));
        $failed = array_keys(array_filter($checks, fn ($ok) => ! $ok));

        return [
            'classification' => $failed ? 'not production ready' : 'configuration checks passed',
            'implemented_modules' => ['identity', 'diocesan structure', 'schools', 'returns', 'statistics', 'documents', 'communications', 'analytics'],
            'security_notes' => array_map(fn ($name) => 'PASS: '.$name, $passed),
            'workflow_notes' => ['Automated checks do not verify real email delivery, TLS termination or backup restoration themselves.'],
            'outstanding_decisions' => array_map(fn ($name) => 'FAIL / UNVERIFIED: '.$name, $failed),
            'technical_debt' => ['Complete staging smoke tests and retain backup restore and email delivery evidence before launch.'],
        ];
    }

    public function createSnapshot(User $user): ProductionReadinessReview
    {
        return ProductionReadinessReview::create([...$this->snapshotPayload(), 'reviewed_by' => $user->id, 'reviewed_at' => now()]);
    }
}
