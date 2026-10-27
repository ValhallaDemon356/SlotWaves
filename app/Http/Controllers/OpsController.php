<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class OpsController extends Controller
{
    /**
     * Authenticate request using OPS_DEPLOY_TOKEN or APP_KEY with constant-time hash_equals.
     */
    protected function authenticateToken(Request $request): ?JsonResponse
    {
        $configuredOpsToken = (string) env('OPS_DEPLOY_TOKEN', config('app.ops_deploy_token', ''));
        $appKey = (string) config('app.key', '');

        $validTokens = [];
        if (trim($configuredOpsToken) !== '') {
            $validTokens[] = trim($configuredOpsToken);
        }
        if (trim($appKey) !== '') {
            $validTokens[] = $appKey;
            $validTokens[] = hash('sha256', 'slotwaves-ops:' . $appKey);
        }

        if (empty($validTokens)) {
            return response()->json([
                'success' => false,
                'error'   => 'Neither OPS_DEPLOY_TOKEN nor APP_KEY is configured on the server environment.',
            ], 500);
        }

        // Retrieve token from X-Ops-Token header, Bearer token, or request body/query
        $providedToken = $request->header('X-Ops-Token');
        if (!$providedToken && $request->bearerToken()) {
            $providedToken = $request->bearerToken();
        }
        if (!$providedToken) {
            $providedToken = $request->input('token');
        }

        if (!$providedToken) {
            return response()->json([
                'success' => false,
                'error'   => 'Unauthorized. Missing deployment token.',
            ], 401);
        }

        $isAuthorized = false;
        foreach ($validTokens as $validToken) {
            if (hash_equals($validToken, (string) $providedToken)) {
                $isAuthorized = true;
                break;
            }
        }

        if (!$isAuthorized) {
            Log::warning('Unauthorized ops migration request attempted.', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Unauthorized. Invalid deployment token.',
            ], 401);
        }

        return null;
    }

    /**
     * Execute database migrations safely in runtime (Vercel Serverless / Cloud Runtime).
     *
     * POST /ops/migrate
     * Headers: X-Ops-Token: <token>
     */
    public function migrate(Request $request): JsonResponse
    {
        $authError = $this->authenticateToken($request);
        if ($authError) {
            return $authError;
        }

        $seed = $request->boolean('seed', false);
        $command = $seed ? 'slotwaves:init-supabase' : 'migrate';
        $params = $seed ? ['--seed' => true, '--force' => true] : ['--force' => true];

        try {
            Log::info("OpsController: Executing '{$command}' via runtime endpoint.");

            $exitCode = Artisan::call($command, $params);
            $output = Artisan::output();

            $status = ($exitCode === 0);

            Log::info("OpsController: Command '{$command}' completed with exit code {$exitCode}.");

            return response()->json([
                'success'    => $status,
                'command'    => $command,
                'exit_code'  => $exitCode,
                'output'     => trim($output),
                'timestamp'  => now()->toISOString(),
            ], $status ? 200 : 500);
        } catch (\Throwable $e) {
            Log::error("OpsController: Migration command threw exception: " . $e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json([
                'success'    => false,
                'command'    => $command,
                'exit_code'  => 1,
                'error'      => $e->getMessage(),
                'timestamp'  => now()->toISOString(),
            ], 500);
        }
    }

    /**
     * Check migration and table status in runtime.
     *
     * GET /ops/migrate/status
     * POST /ops/migrate/status
     */
    public function status(Request $request): JsonResponse
    {
        $authError = $this->authenticateToken($request);
        if ($authError) {
            return $authError;
        }

        try {
            $exitCode = Artisan::call('migrate:status');
            $output = Artisan::output();

            $tables = [
                'uploads',
                'flights',
                'airports',
                'airlines',
                'flight_pairings',
                'timeline_positions',
                'timeline_settings',
                'sessions',
                'cache',
                'upload_chunks',
                'fdr_processing_jobs',
                'fdr_upload_sessions',
                'fdr_flights',
            ];

            $schemaStatus = [];
            foreach ($tables as $tbl) {
                try {
                    $schemaStatus[$tbl] = \Illuminate\Support\Facades\Schema::hasTable($tbl);
                } catch (\Throwable $se) {
                    $schemaStatus[$tbl] = false;
                }
            }

            return response()->json([
                'success'       => ($exitCode === 0),
                'command'       => 'migrate:status',
                'exit_code'     => $exitCode,
                'tables'        => $schemaStatus,
                'output'        => trim($output),
                'timestamp'     => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success'    => false,
                'error'      => \App\Services\FlightDailyReport\FdrDatabaseService::sanitizeLogMessage($e->getMessage()),
                'timestamp'  => now()->toISOString(),
            ], 500);
        }
    }
}
