<?php

namespace App\Outreach;

use App\Outreach\Contracts\GoogleSheetReader;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleSheetsLeadSource implements GoogleSheetReader
{
    /** @return array<int, array<int, mixed>> */
    public function rows(): array
    {
        $spreadsheetId = trim((string) config('outreach.google_sheets.spreadsheet_id'));
        $range = trim((string) config('outreach.google_sheets.range'));
        $credentialsPath = trim((string) config('outreach.google_sheets.service_account_json'));

        $missing = collect([
            'GOOGLE_SHEETS_SPREADSHEET_ID' => $spreadsheetId,
            'GOOGLE_SHEETS_RANGE' => $range,
            'GOOGLE_SERVICE_ACCOUNT_JSON' => $credentialsPath,
        ])->filter(fn (string $value): bool => $value === '')->keys();

        if ($missing->isNotEmpty()) {
            throw new RuntimeException('Missing Google Sheets configuration: '.$missing->implode(', '));
        }

        if (! str_starts_with($credentialsPath, DIRECTORY_SEPARATOR)) {
            $credentialsPath = base_path($credentialsPath);
        }

        if (! is_file($credentialsPath) || ! is_readable($credentialsPath)) {
            throw new RuntimeException(
                'The Google service-account JSON file is missing or unreadable at the configured GOOGLE_SERVICE_ACCOUNT_JSON path.',
            );
        }

        $credentials = new ServiceAccountCredentials(
            'https://www.googleapis.com/auth/spreadsheets.readonly',
            $credentialsPath,
        );
        $token = $credentials->fetchAuthToken();
        $accessToken = $token['access_token'] ?? null;

        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Google did not return an access token for the configured service account.');
        }

        $response = Http::acceptJson()
            ->withToken($accessToken)
            ->timeout(20)
            ->retry(2, 250)
            ->get(
                'https://sheets.googleapis.com/v4/spreadsheets/'
                    .rawurlencode($spreadsheetId)
                    .'/values/'
                    .rawurlencode($range),
                ['majorDimension' => 'ROWS'],
            );

        if (! $response->successful()) {
            throw new RuntimeException("Google Sheets API request failed with HTTP {$response->status()}.");
        }

        $rows = $response->json('values', []);

        return is_array($rows) ? $rows : [];
    }
}
