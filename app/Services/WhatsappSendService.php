<?php

namespace App\Services;

use App\Models\UserDetail;
use App\Models\WhatsappConfiguration;
use Illuminate\Support\Facades\Http;

class WhatsappSendService
{
    /**
     * @param  array<string, string>  $replacements
     * @return array{0:bool,1:string}
     */
    public function send(WhatsappConfiguration $config, UserDetail $user, array $replacements): array
    {
        $mobile = preg_replace('/\s+/', '', (string) ($user->Mobile ?? ''));
        if ($mobile === '') {
            return [false, 'Cannot send WhatsApp: mobile number missing for RegID ' . $user->RegID . '.'];
        }

        $apiKey = trim((string) $config->api_key);
        if ($apiKey === '') {
            return [false, 'Cannot send WhatsApp: API key is not configured for "' . $config->name . '".'];
        }

        return match ($config->provider) {
            WhatsappConfiguration::PROVIDER_AISENSY => $this->sendAisensy($config, $user, $mobile, $replacements, $apiKey),
            WhatsappConfiguration::PROVIDER_INTERAKT => $this->sendInterakt($config, $user, $mobile, $replacements, $apiKey),
            default => [false, 'Unsupported WhatsApp provider: ' . $config->provider],
        };
    }

    /**
     * @param  array<string, string>  $replacements
     * @return array{0:bool,1:string}
     */
    protected function sendAisensy(
        WhatsappConfiguration $config,
        UserDetail $user,
        string $mobile,
        array $replacements,
        string $apiKey
    ): array {
        if (trim((string) $config->campaign_name) === '') {
            return [false, 'Cannot send WhatsApp: AiSensy campaign name is required.'];
        }

        $payload = [
            'apiKey' => $apiKey,
            'campaignName' => (string) $config->campaign_name,
            'destination' => $this->formatDestination($mobile, (string) $config->default_country_code),
            'userName' => (string) ($user->Name ?: $user->RegID),
        ];

        if ($config->source) {
            $payload['source'] = (string) $config->source;
        }

        $templateParams = $this->resolvePlaceholderLines($config->template_params, $replacements);
        if ($templateParams !== []) {
            $payload['templateParams'] = $templateParams;
        }

        if ($config->include_media && $config->media_url_param) {
            $mediaUrl = $this->resolvePlaceholders((string) $config->media_url_param, $replacements);
            if ($mediaUrl !== '') {
                $payload['media'] = [
                    'url' => $mediaUrl,
                    'filename' => $this->resolvePlaceholders(
                        (string) ($config->media_filename ?: 'e_badge_{{RegID}}.pdf'),
                        $replacements
                    ),
                ];
            }
        }

        $tags = $this->parseTags($config->tags);
        if ($tags !== []) {
            $payload['tags'] = $tags;
        }

        $attributes = $this->resolveAttributes($config->attributes, $replacements);
        if ($attributes !== []) {
            $payload['attributes'] = $attributes;
        }

        return $this->postJson(
            $config,
            $config->defaultApiUrl(),
            $payload
        );
    }

    /**
     * @param  array<string, string>  $replacements
     * @return array{0:bool,1:string}
     */
    protected function sendInterakt(
        WhatsappConfiguration $config,
        UserDetail $user,
        string $mobile,
        array $replacements,
        string $apiKey
    ): array {
        if (trim((string) $config->template_name) === '') {
            return [false, 'Cannot send WhatsApp: Interakt template name is required.'];
        }

        [$countryCode, $phoneNumber] = $this->splitPhoneForInterakt($mobile, (string) $config->default_country_code);

        $template = [
            'name' => (string) $config->template_name,
            'languageCode' => (string) ($config->language_code ?: 'en'),
        ];

        $headerValues = $this->resolvePlaceholderLines($config->header_params, $replacements);
        if ($headerValues !== []) {
            $template['headerValues'] = $headerValues;
        }

        $bodyValues = $this->resolvePlaceholderLines($config->body_params, $replacements);
        if ($bodyValues !== []) {
            $template['bodyValues'] = $bodyValues;
        }

        $payload = [
            'countryCode' => $countryCode,
            'phoneNumber' => $phoneNumber,
            'type' => 'Template',
            'template' => $template,
        ];

        if ($config->callback_data) {
            $payload['callbackData'] = (string) $config->callback_data;
        }

        return $this->postJson(
            $config,
            $config->defaultApiUrl(),
            $payload,
            [
                'Authorization' => 'Basic ' . $apiKey,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $extraHeaders
     * @return array{0:bool,1:string}
     */
    protected function postJson(
        WhatsappConfiguration $config,
        string $url,
        array $payload,
        array $extraHeaders = []
    ): array {
        /** @var \Illuminate\Http\Client\Response $response */
        $response = Http::withOptions([
            'verify' => (bool) $config->ssl_verify,
        ])->withHeaders(array_merge([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $extraHeaders))->post($url, $payload);

        if (!$response->successful()) {
            return [false, 'WhatsApp send failed: ' . $response->status() . ' ' . $response->body()];
        }

        return [true, 'WhatsApp message sent successfully.'];
    }

    /**
     * @param  array<string, string>  $replacements
     * @return array<int, string>
     */
    protected function resolvePlaceholderLines(?string $text, array $replacements): array
    {
        if (!$text) {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];

        return array_values(array_filter(array_map(
            fn (string $line) => $this->resolvePlaceholders(trim($line), $replacements),
            $lines
        ), fn (string $line) => $line !== ''));
    }

    /**
     * @param  array<string, string>  $replacements
     */
    protected function resolvePlaceholders(string $template, array $replacements): string
    {
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * @param  array<string, string>|null  $attributes
     * @param  array<string, string>  $replacements
     * @return array<string, string>
     */
    protected function resolveAttributes(?array $attributes, array $replacements): array
    {
        if (!$attributes) {
            return [];
        }

        $resolved = [];
        foreach ($attributes as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                continue;
            }
            $resolved[$key] = $this->resolvePlaceholders($value, $replacements);
        }

        return $resolved;
    }

    /**
     * @return array<int, string>
     */
    protected function parseTags(?string $tags): array
    {
        if (!$tags) {
            return [];
        }

        $decoded = json_decode($tags, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('strval', $decoded)));
        }

        return array_values(array_filter(array_map('trim', explode(',', $tags))));
    }

    protected function formatDestination(string $mobile, string $defaultCountryCode): string
    {
        $trimmed = trim($mobile);
        $digits = preg_replace('/\D+/', '', $trimmed) ?: '';

        if (str_starts_with($trimmed, '+')) {
            return '+' . $digits;
        }

        $countryDigits = ltrim($defaultCountryCode, '+');
        if (strlen($digits) === 10 && $countryDigits !== '') {
            return '+' . $countryDigits . $digits;
        }

        if ($countryDigits !== '' && !str_starts_with($digits, $countryDigits)) {
            return '+' . $countryDigits . $digits;
        }

        return '+' . $digits;
    }

    /**
     * @return array{0:string,1:string}
     */
    protected function splitPhoneForInterakt(string $mobile, string $defaultCountryCode): array
    {
        $trimmed = trim($mobile);

        if (str_starts_with($trimmed, '+')) {
            $digits = preg_replace('/\D+/', '', $trimmed) ?: '';
            if (strlen($digits) > 10) {
                return ['+' . substr($digits, 0, strlen($digits) - 10), substr($digits, -10)];
            }

            return [$defaultCountryCode, $digits];
        }

        $digits = preg_replace('/\D+/', '', $trimmed) ?: '';
        if (strlen($digits) > 10) {
            return ['+' . substr($digits, 0, strlen($digits) - 10), substr($digits, -10)];
        }

        return [$defaultCountryCode, $digits];
    }
}
