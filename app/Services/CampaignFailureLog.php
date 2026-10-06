<?php

namespace App\Services;

use App\Models\Campaign;

class CampaignFailureLog
{
    public static function path(int $campaignId): string
    {
        return storage_path("logs/campaign_{$campaignId}.log");
    }

    public static function reset(int $campaignId): void
    {
        file_put_contents(self::path($campaignId), '');
    }

    public static function failure(Campaign $campaign, string $email, \Throwable $e): void
    {
        $line = sprintf(
            "[%s] %s | %s: %s\n",
            now()->format('Y-m-d H:i:s'),
            $email,
            class_basename($e),
            preg_replace('/\s+/', ' ', trim($e->getMessage()))
        );

        file_put_contents(self::path($campaign->id), $line, FILE_APPEND | LOCK_EX);
    }
}
