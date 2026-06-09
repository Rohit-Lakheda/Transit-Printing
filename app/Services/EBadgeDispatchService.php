<?php

namespace App\Services;

use App\Models\Category;
use App\Models\EBadgeLayoutSetting;
use App\Models\EBadgeMailLog;
use App\Models\EBadgeSetting;
use App\Models\EventSetting;
use App\Models\MailConfiguration;
use App\Models\UserDetail;
use App\Models\WhatsappConfiguration;
use App\Support\PublicStorageUrl;
use Illuminate\Support\Facades\Storage;

class EBadgeDispatchService
{
    public function __construct(
        protected EBadgePdfService $pdfService,
        protected ConfiguredMailerService $mailerService,
        protected WhatsappSendService $whatsappService
    ) {
    }

    /**
     * Send email and WhatsApp when enabled in settings (API registration hook).
     *
     * @return array{email: array{sent: bool, message: string}, whatsapp: array{sent: bool, message: string}}
     */
    public function sendOnApiRegistration(UserDetail $user): array
    {
        $setting = EBadgeSetting::getDefault();
        $result = [
            'email' => ['sent' => false, 'message' => 'Auto email disabled.'],
            'whatsapp' => ['sent' => false, 'message' => 'Auto WhatsApp disabled.'],
        ];

        if ($setting->auto_send_email_on_api_registration) {
            [$sent, $message] = $this->sendEmailToUser($user);
            $result['email'] = ['sent' => $sent, 'message' => $message];
        }

        if ($setting->auto_send_whatsapp_on_api_registration) {
            [$sent, $message] = $this->sendWhatsappToUser($user);
            $result['whatsapp'] = ['sent' => $sent, 'message' => $message];
        }

        return $result;
    }

    /**
     * @return array{0:bool,1:string}
     */
    public function sendEmailToUser(UserDetail $user): array
    {
        $logBase = [
            'user_detail_id' => $user->id,
            'regid' => $user->RegID,
            'category' => $user->Category,
            'email' => $user->Email,
            'sent_at' => now(),
        ];

        if (!$user->Email) {
            EBadgeMailLog::create(array_merge($logBase, [
                'status' => 'failed',
                'message' => 'User email is missing.',
            ]));

            return [false, 'Cannot send: user email is missing for RegID ' . $user->RegID . '.'];
        }

        $setting = EBadgeSetting::getDefault();
        $mailConfig = null;
        if ($setting->mail_configuration_id) {
            $mailConfig = MailConfiguration::find($setting->mail_configuration_id);
        }
        if (!$mailConfig) {
            $mailConfig = MailConfiguration::where('is_active', true)->orderByDesc('id')->first()
                ?? MailConfiguration::orderByDesc('id')->first();
        }
        if (!$mailConfig) {
            EBadgeMailLog::create(array_merge($logBase, [
                'status' => 'failed',
                'message' => 'Mail configuration not found.',
            ]));

            return [false, 'Cannot send: no mail configuration available.'];
        }

        $category = Category::where('Category', $user->Category)->first();
        if (!$category) {
            EBadgeMailLog::create(array_merge($logBase, [
                'status' => 'failed',
                'message' => 'Category not found.',
            ]));

            return [false, 'Cannot send: category not found for user ' . $user->RegID . '.'];
        }

        if (!$category->e_badge_background_path) {
            EBadgeMailLog::create(array_merge($logBase, [
                'status' => 'failed',
                'message' => 'E-badge background not configured for category.',
            ]));

            return [false, 'Cannot send: e-badge background missing for category ' . $category->Category . '.'];
        }

        if (!$this->isBackgroundRenderable($category)) {
            EBadgeMailLog::create(array_merge($logBase, [
                'status' => 'failed',
                'message' => 'Background format unsupported on server. Upload PNG background.',
            ]));

            return [false, 'Cannot send: background format is unsupported by server for category ' . $category->Category . '. Please upload PNG background.'];
        }

        $hasLayout = EBadgeLayoutSetting::where('Category', $category->Category)->exists();
        if (!$hasLayout) {
            EBadgeMailLog::create(array_merge($logBase, [
                'status' => 'failed',
                'message' => 'E-badge layout not configured for category.',
            ]));

            return [false, 'Cannot send: e-badge layout missing for category ' . $category->Category . '.'];
        }

        try {
            $pdfPayload = $this->buildStoredPdfPayload($user);
            $replacements = $this->buildTemplateReplacements($user, $category, $pdfPayload['url']);
            $subject = str_replace(array_keys($replacements), array_values($replacements), $setting->email_subject ?: 'Your E-Badge');
            $body = str_replace(array_keys($replacements), array_values($replacements), $setting->email_body ?: '<p>Please find your e-badge attached.</p>');

            $this->mailerService->sendHtml(
                $user->Email,
                $subject,
                $body,
                $mailConfig,
                [[
                    'content' => $pdfPayload['content'],
                    'filename' => $pdfPayload['filename'],
                    'mime' => 'application/pdf',
                ]]
            );

            EBadgeMailLog::create(array_merge($logBase, [
                'status' => 'success',
                'message' => 'E-badge sent successfully.',
            ]));

            return [true, 'E-badge sent successfully to ' . $user->Email . '.'];
        } catch (\Throwable $e) {
            EBadgeMailLog::create(array_merge($logBase, [
                'status' => 'failed',
                'message' => $e->getMessage(),
            ]));

            return [false, 'Failed to send e-badge for ' . $user->RegID . ': ' . $e->getMessage()];
        }
    }

    /**
     * @return array{0:bool,1:string}
     */
    public function sendWhatsappToUser(UserDetail $user): array
    {
        $mobile = preg_replace('/\s+/', '', (string) ($user->Mobile ?? ''));
        if ($mobile === '') {
            return [false, 'Cannot send WhatsApp: mobile number missing for RegID ' . $user->RegID . '.'];
        }

        $whatsappConfig = $this->resolveWhatsappConfiguration();
        if (!$whatsappConfig) {
            return [false, 'Cannot send WhatsApp: no WhatsApp configuration found. Set it up under E-Badge Settings.'];
        }

        $category = Category::where('Category', $user->Category)->first();
        if (!$category) {
            return [false, 'Cannot send WhatsApp: category not found for user ' . $user->RegID . '.'];
        }
        if (!$category->e_badge_background_path) {
            return [false, 'Cannot send WhatsApp: e-badge background missing for category ' . $category->Category . '.'];
        }
        if (!$this->isBackgroundRenderable($category)) {
            return [false, 'Cannot send WhatsApp: background format unsupported for category ' . $category->Category . '. Please upload PNG.'];
        }

        $pdfPayload = $this->buildStoredPdfPayload($user);
        $replacements = $this->buildTemplateReplacements($user, $category, $pdfPayload['url']);

        [$ok, $message] = $this->whatsappService->send($whatsappConfig, $user, $replacements);

        if ($ok) {
            return [true, $message . ' Sent to ' . $mobile . ' via ' . $whatsappConfig->providerLabel() . '.'];
        }

        return [false, 'WhatsApp send failed for ' . $user->RegID . ': ' . $message];
    }

    /**
     * @return array{content:string,filename:string,storage_path:string,url:string}
     */
    public function buildStoredPdfPayload(UserDetail $user): array
    {
        $pdf = $this->pdfService->generateForUser($user);
        $pdfStoragePath = 'e-badge-pdfs/' . ($user->RegID ?: $user->id) . '_' . now()->format('Ymd_His') . '.pdf';
        Storage::disk('public')->put($pdfStoragePath, $pdf['content']);

        return [
            'content' => $pdf['content'],
            'filename' => $pdf['filename'],
            'storage_path' => $pdfStoragePath,
            'url' => PublicStorageUrl::make($pdfStoragePath),
        ];
    }

    protected function resolveWhatsappConfiguration(): ?WhatsappConfiguration
    {
        $setting = EBadgeSetting::getDefault();
        if ($setting->whatsapp_configuration_id) {
            $selected = WhatsappConfiguration::find($setting->whatsapp_configuration_id);
            if ($selected) {
                return $selected;
            }
        }

        return WhatsappConfiguration::where('is_active', true)->orderByDesc('id')->first()
            ?? WhatsappConfiguration::orderByDesc('id')->first();
    }

    /**
     * @return array<string,string>
     */
    public function buildTemplateReplacements(UserDetail $user, Category $category, string $badgeDownloadLink = ''): array
    {
        $eventSettings = EventSetting::getSettings();
        $eventLogoUrl = $eventSettings->logo_path ? PublicStorageUrl::make($eventSettings->logo_path) : '';
        $emailLogoUrl = $eventSettings->email_logo_path ? PublicStorageUrl::make($eventSettings->email_logo_path) : '';
        $emailLogoImage = $emailLogoUrl !== ''
            ? '<img src="' . e($emailLogoUrl) . '" alt="Email Logo" style="max-width:220px;height:auto;">'
            : '';

        return [
            '{{Name}}' => $user->Name ?? '',
            '{{RegID}}' => $user->RegID ?? '',
            '{{Category}}' => $user->Category ?? '',
            '{{Company}}' => $user->Company ?? '',
            '{{Email}}' => $user->Email ?? '',
            '{{Mobile}}' => $user->Mobile ?? '',
            '{{Designation}}' => $user->Designation ?? '',
            '{{Country}}' => $user->Country ?? '',
            '{{State}}' => $user->State ?? '',
            '{{City}}' => $user->City ?? '',
            '{{Additional1}}' => $user->Additional1 ?? '',
            '{{Additional2}}' => $user->Additional2 ?? '',
            '{{Additional3}}' => $user->Additional3 ?? '',
            '{{Additional4}}' => $user->Additional4 ?? '',
            '{{Additional5}}' => $user->Additional5 ?? '',
            '{{EventLogoUrl}}' => $eventLogoUrl,
            '{{EmailLogoUrl}}' => $emailLogoUrl,
            '{{EmailLogoImage}}' => $emailLogoImage,
            '{{BadgeDownloadLink}}' => $badgeDownloadLink,
            '{{BadgeBackgroundUrl}}' => $category->e_badge_background_path ? PublicStorageUrl::make($category->e_badge_background_path) : '',
        ];
    }

    public function isBackgroundRenderable(Category $category): bool
    {
        if (!$category->e_badge_background_path) {
            return false;
        }

        $ext = strtolower((string) pathinfo($category->e_badge_background_path, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg'], true)) {
            return function_exists('imagecreatefromjpeg');
        }
        if ($ext === 'png') {
            return function_exists('imagecreatefrompng');
        }
        if ($ext === 'gif') {
            return function_exists('imagecreatefromgif');
        }
        if ($ext === 'webp') {
            return function_exists('imagecreatefromwebp');
        }

        return false;
    }
}
