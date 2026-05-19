<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SystemHealthController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/System/Health', [
            'checks' => [
                'database' => $this->checkDb(),
                'redis'    => $this->checkRedis(),
                'horizon'  => $this->checkHorizon(),
                'reverb'   => $this->checkReverb(),
                'storage'  => $this->checkStorage(),
            ],
            'php' => [
                'version'      => PHP_VERSION,
                'swoole'       => extension_loaded('swoole'),
                'opcache'      => function_exists('opcache_get_status') ? (bool) @opcache_get_status(false) : false,
                'memory_limit' => ini_get('memory_limit'),
            ],
            'octane' => [
                'server'  => config('octane.server', 'not installed'),
                'enabled' => class_exists(\Laravel\Octane\Octane::class ?? ''),
            ],
        ]);
    }

    protected function checkDb(): array
    {
        try {
            $version = DB::selectOne('select version()')?->version ?? 'unknown';
            $schemas = DB::select("select count(*) as c from information_schema.schemata where schema_name like 'tenant_%'");
            return ['ok' => true, 'detail' => $version, 'tenant_schemas' => (int) ($schemas[0]->c ?? 0)];
        } catch (\Throwable $e) {
            return ['ok' => false, 'detail' => $e->getMessage()];
        }
    }

    protected function checkRedis(): array
    {
        try {
            if (! extension_loaded('redis')) {
                return ['ok' => false, 'detail' => 'phpredis extension not loaded'];
            }
            $pong = \Illuminate\Support\Facades\Redis::connection()->ping();
            return ['ok' => true, 'detail' => 'PONG'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'detail' => 'Redis not connected: ' . $e->getMessage()];
        }
    }

    protected function checkHorizon(): array
    {
        try {
            if (! class_exists(\Laravel\Horizon\Contracts\MasterSupervisorRepository::class)) {
                return ['ok' => false, 'detail' => 'Horizon not installed'];
            }
            $status = app(\Laravel\Horizon\Contracts\MasterSupervisorRepository::class)->all();
            return ['ok' => !empty($status), 'detail' => count($status) . ' supervisor(s)'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'detail' => 'Horizon not running'];
        }
    }

    protected function checkReverb(): array
    {
        $host = (string) config('reverb.servers.reverb.host', '0.0.0.0');
        $port = (int) config('reverb.servers.reverb.port', 8080);
        try {
            $fp = @fsockopen($host, $port, $errno, $errstr, 1.0);
            if (! $fp) return ['ok' => false, 'detail' => "Not listening on {$host}:{$port}"];
            fclose($fp);
            return ['ok' => true, 'detail' => "Listening on {$host}:{$port}"];
        } catch (\Throwable $e) {
            return ['ok' => false, 'detail' => 'Reverb not running'];
        }
    }

    protected function checkStorage(): array
    {
        $path = storage_path('app');
        return ['ok' => is_writable($path), 'detail' => $path . ' ' . (is_writable($path) ? 'writable' : 'NOT writable')];
    }
}
