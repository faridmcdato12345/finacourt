<?php

namespace App\BookingLinks;

use InvalidArgumentException;

class ExternalBookingUrl
{
    public function normalize(string $value): string
    {
        $url = trim($value);

        if ($url === ''
            || preg_match('/[\x00-\x1F\x7F\\\\]/', $url) === 1
            || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('Enter a complete, valid HTTPS booking URL.');
        }

        $parts = parse_url($url);

        if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            throw new InvalidArgumentException('The booking URL must begin with https://.');
        }

        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));

        if ($host === '' || isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('Enter a public booking URL without embedded login details.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false
            || $host === 'localhost'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || ! str_contains($host, '.')) {
            throw new InvalidArgumentException('The booking URL must use a public website hostname.');
        }

        $applicationHost = strtolower(rtrim((string) parse_url((string) config('app.url'), PHP_URL_HOST), '.'));

        if ($applicationHost !== '' && $this->comparableHost($host) === $this->comparableHost($applicationHost)) {
            throw new InvalidArgumentException('Use the external platform URL, not another FinACourt link.');
        }

        $port = isset($parts['port']) && (int) $parts['port'] !== 443
            ? ':'.(int) $parts['port']
            : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return "https://{$host}{$port}{$path}{$query}{$fragment}";
    }

    private function comparableHost(string $host): string
    {
        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }
}
