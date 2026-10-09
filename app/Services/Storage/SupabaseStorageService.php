<?php

namespace App\Services\Storage;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class SupabaseStorageService
{
    protected string $supabaseUrl;
    protected string $serviceRoleKey;
    protected string $anonKey;
    protected string $defaultBucket;
    protected Client $httpClient;

    public function __construct(?Client $httpClient = null)
    {
        $this->supabaseUrl    = rtrim(config('services.supabase.url', env('SUPABASE_URL', '')), '/');
        $this->serviceRoleKey = config('services.supabase.service_role_key', env('SUPABASE_SERVICE_ROLE_KEY', ''));
        $this->anonKey        = config('services.supabase.anon_key', env('SUPABASE_ANON_KEY', ''));
        $this->defaultBucket  = config('services.supabase.bucket', 'fdr-datasets');

        $this->httpClient = $httpClient ?: new Client([
            'timeout'         => 120, // generous timeout for large files on Vercel
            'connect_timeout' => 15,
            'http_errors'     => false,
        ]);
    }

    /**
     * Download a file from Supabase Storage into a local temporary file on Vercel (/tmp).
     * Uses streaming sink so that the 40-100MB payload never loads into PHP RAM.
     *
     * @param string $filePath Storage object path (e.g. raw_fdr/2026/08/lion_cgk.xlsx)
     * @param string|null $bucket Bucket name, defaults to configured bucket
     * @return string Absolute path to local temporary file in /tmp
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function downloadToTemp(string $filePath, ?string $bucket = null): string
    {
        $bucket = $bucket ?: $this->defaultBucket;
        $cleanPath = $this->sanitizeStoragePath($filePath);

        if (empty($this->supabaseUrl)) {
            throw new RuntimeException("Supabase URL is not configured. Please set SUPABASE_URL in your environment.");
        }

        // Prefer service_role key for backend operations; fallback to anon key
        $apiKey = !empty($this->serviceRoleKey) ? $this->serviceRoleKey : $this->anonKey;

        // Vercel serverless writable directory is /tmp (sys_get_temp_dir() returns /tmp in Linux/Vercel)
        $tempDir = sys_get_temp_dir();
        $ext = pathinfo($cleanPath, PATHINFO_EXTENSION);
        $tempFile = tempnam($tempDir, 'fdr_stream_');
        if ($ext) {
            $tempFileWithExt = $tempFile . '.' . strtolower($ext);
            rename($tempFile, $tempFileWithExt);
            $tempFile = $tempFileWithExt;
        }

        $endpoint = "{$this->supabaseUrl}/storage/v1/object/authenticated/{$bucket}/{$cleanPath}";

        Log::info("SupabaseStorageService: Streaming download started", [
            'bucket'     => $bucket,
            'path'       => $cleanPath,
            'target_tmp' => $tempFile,
        ]);

        try {
            $headers = [
                'apikey' => $apiKey,
            ];
            if (!empty($apiKey)) {
                $headers['Authorization'] = "Bearer {$apiKey}";
            }

            $response = $this->httpClient->request('GET', $endpoint, [
                'headers' => $headers,
                'sink'    => $tempFile, // stream response directly to disk!
            ]);

            $statusCode = $response->getStatusCode();

            // If authenticated endpoint returns 404/401 and bucket is public, attempt public endpoint fallback
            if ($statusCode !== 200) {
                $publicEndpoint = "{$this->supabaseUrl}/storage/v1/object/public/{$bucket}/{$cleanPath}";
                $response = $this->httpClient->request('GET', $publicEndpoint, [
                    'headers' => ['apikey' => $apiKey],
                    'sink'    => $tempFile,
                ]);
                $statusCode = $response->getStatusCode();
            }

            if ($statusCode !== 200) {
                @unlink($tempFile);
                $bodyPreview = '';
                if (file_exists($tempFile)) {
                    $bodyPreview = file_get_contents($tempFile, false, null, 0, 512);
                    @unlink($tempFile);
                }
                throw new RuntimeException("Failed to download file from Supabase Storage (HTTP {$statusCode}): {$bodyPreview}");
            }

            if (!file_exists($tempFile) || filesize($tempFile) === 0) {
                @unlink($tempFile);
                throw new RuntimeException("Downloaded file from Supabase Storage is empty or unreadable.");
            }

            Log::info("SupabaseStorageService: Download completed successfully", [
                'bytes' => filesize($tempFile),
                'file'  => $tempFile,
            ]);

            return $tempFile;

        } catch (GuzzleException $e) {
            @unlink($tempFile);
            Log::error("SupabaseStorageService GuzzleException: " . $e->getMessage(), [
                'bucket' => $bucket,
                'path'   => $cleanPath,
            ]);
            throw new RuntimeException("Connection error downloading from Supabase Storage: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Sanitize and validate object path against path traversal attacks.
     */
    public function sanitizeStoragePath(string $filePath): string
    {
        $filePath = trim($filePath);

        // Strip leading slash and whitespace
        $filePath = ltrim($filePath, '/\\');

        // Check for directory traversal sequences
        if (str_contains($filePath, '..') || str_contains($filePath, "\0")) {
            throw new InvalidArgumentException("Invalid storage file path: directory traversal attempt detected.");
        }

        // Validate characters
        if (preg_match('/[<>:"|?*]/', $filePath)) {
            throw new InvalidArgumentException("Invalid characters detected in storage file path.");
        }

        return $filePath;
    }

    /**
     * Delete an object from Supabase Storage.
     */
    public function deleteObject(string $filePath, ?string $bucket = null): bool
    {
        $bucket = $bucket ?: $this->defaultBucket;
        $cleanPath = $this->sanitizeStoragePath($filePath);
        $apiKey = !empty($this->serviceRoleKey) ? $this->serviceRoleKey : $this->anonKey;

        $endpoint = "{$this->supabaseUrl}/storage/v1/object/{$bucket}/{$cleanPath}";

        try {
            $res = $this->httpClient->request('DELETE', $endpoint, [
                'headers' => [
                    'apikey'        => $apiKey,
                    'Authorization' => "Bearer {$apiKey}",
                ],
            ]);
            return $res->getStatusCode() >= 200 && $res->getStatusCode() < 300;
        } catch (\Throwable $e) {
            Log::warning("SupabaseStorageService deleteObject failed: " . $e->getMessage());
            return false;
        }
    }
}
