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
        $this->defaultBucket  = config('services.supabase.bucket', env('NEXT_PUBLIC_SUPABASE_STORAGE_BUCKET', 'flight-reports'));

        $this->httpClient = $httpClient ?: new Client([
            'timeout'         => 120, // generous timeout for large files on Vercel
            'connect_timeout' => 15,
            'http_errors'     => false,
        ]);
    }

    /**
     * Create a signed upload URL using Supabase Storage API.
     * Allows client browser to upload large files directly without exposing service_role credentials or hitting RLS limits.
     *
     * @param string $filePath Destination path in storage bucket
     * @param string|null $bucket Bucket name, defaults to configured bucket
     * @param int $expiresIn Expiration time in seconds (default 3600)
     * @param bool $upsert Allow overwriting existing files
     * @return array Contains signed_url, token, path, and bucket
     */
    public function createSignedUploadUrl(string $filePath, ?string $bucket = null, int $expiresIn = 3600, bool $upsert = true): array
    {
        $bucket = $bucket ?: $this->defaultBucket;
        $cleanPath = $this->sanitizeStoragePath($filePath);

        if (empty($this->supabaseUrl)) {
            throw new RuntimeException("FDR_STORAGE_ERROR: Supabase URL is not configured. Set SUPABASE_URL in environment.");
        }

        $apiKey = !empty($this->serviceRoleKey) ? $this->serviceRoleKey : $this->anonKey;
        if (empty($apiKey)) {
            throw new RuntimeException("FDR_STORAGE_ERROR: Neither SUPABASE_SERVICE_ROLE_KEY nor SUPABASE_ANON_KEY is configured.");
        }

        $endpoint = "{$this->supabaseUrl}/storage/v1/object/upload/sign/{$bucket}/{$cleanPath}";

        try {
            $response = $this->httpClient->request('POST', $endpoint, [
                'headers' => [
                    'apikey'        => $apiKey,
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'upsert' => $upsert,
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            if ($statusCode < 200 || $statusCode >= 300 || empty($data['url'])) {
                $errDetail = $data['message'] ?? ($data['error'] ?? $body);
                if ($statusCode === 404 || str_contains($body, 'NoSuchBucket') || str_contains(strtolower($body), 'bucket not found')) {
                    throw new RuntimeException("FDR_STORAGE_ERROR: Bucket '{$bucket}' tidak ditemukan di Supabase Storage (404). Pastikan bucket '{$bucket}' ada di Supabase Dashboard.");
                }
                if ($statusCode === 403 || str_contains(strtolower($body), 'row-level security') || str_contains(strtolower($body), 'unauthorized')) {
                    throw new RuntimeException("FDR_STORAGE_ERROR: Akses ditolak RLS Policy Supabase Storage (HTTP 403): {$errDetail}");
                }
                throw new RuntimeException("FDR_SIGNED_UPLOAD_ERROR: Supabase Storage returned HTTP {$statusCode}: {$errDetail}");
            }

            // Construct full signed PUT upload URL
            // Supabase returns relative url e.g. /object/upload/sign/bucket/path?token=xyz or /bucket/path?token=xyz
            $token = $data['token'] ?? null;
            if (!$token && preg_match('/token=([^&]+)/', $data['url'], $m)) {
                $token = $m[1];
            }

            $rawUrl = $data['url'];
            $signedUploadUrl = str_starts_with($rawUrl, 'http')
                ? $rawUrl
                : "{$this->supabaseUrl}/storage/v1" . (str_starts_with($rawUrl, '/') ? '' : '/') . $rawUrl;

            return [
                'success'           => true,
                'signed_url'        => $signedUploadUrl,
                'token'             => $token,
                'path'              => $cleanPath,
                'bucket'            => $bucket,
                'storage_full_path' => "supabase://{$bucket}/{$cleanPath}",
            ];

        } catch (GuzzleException $e) {
            Log::error("Supabase createSignedUploadUrl error: " . $e->getMessage(), [
                'bucket' => $bucket,
                'path'   => $cleanPath,
            ]);
            throw new RuntimeException("FDR_SIGNED_UPLOAD_ERROR: Failed to connect to Supabase Storage: " . $e->getMessage(), 0, $e);
        }
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
            throw new RuntimeException("FDR_STORAGE_ERROR: Supabase URL is not configured. Please set SUPABASE_URL in your environment.");
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
                $bodyPreview = '';
                if (file_exists($tempFile)) {
                    $bodyPreview = file_get_contents($tempFile, false, null, 0, 512);
                    @unlink($tempFile);
                }
                if ($statusCode === 404 || str_contains($bodyPreview, 'NoSuchBucket') || str_contains(strtolower($bodyPreview), 'bucket not found')) {
                    throw new RuntimeException("FDR_STORAGE_ERROR: Bucket '{$bucket}' tidak ditemukan di Supabase Storage (NoSuchBucket/404). Silakan pastikan bucket '{$bucket}' telah dibuat di Supabase Dashboard (Storage -> New Bucket) dan NEXT_PUBLIC_SUPABASE_STORAGE_BUCKET={$bucket} terkonfigurasi di file .env.");
                }
                if ($statusCode === 403 || str_contains(strtolower($bodyPreview), 'row-level security') || str_contains(strtolower($bodyPreview), 'unauthorized')) {
                    throw new RuntimeException("FDR_STORAGE_ERROR: Akses ditolak oleh RLS Policy Supabase Storage untuk bucket '{$bucket}' (HTTP 403). Pastikan bucket memiliki izin SELECT/READ untuk role anon/service_role.");
                }
                throw new RuntimeException("FDR_STORAGE_ERROR: Failed to download file from Supabase Storage (HTTP {$statusCode}): " . ($bodyPreview ?: 'Empty response body'));
            }

            if (!file_exists($tempFile) || filesize($tempFile) === 0) {
                @unlink($tempFile);
                throw new RuntimeException("FDR_STORAGE_ERROR: Downloaded file from Supabase Storage is empty (0 bytes). Check that object exists at '{$cleanPath}'.");
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
            throw new RuntimeException("FDR_STORAGE_ERROR: Connection error downloading from Supabase Storage: " . $e->getMessage(), 0, $e);
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
