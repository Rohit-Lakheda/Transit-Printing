<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\EBadgeLayoutSetting;
use App\Models\EBadgeMailLog;
use App\Models\EBadgeSetting;
use App\Models\EventSetting;
use App\Models\MailConfiguration;
use App\Models\UserDetail;
use App\Services\ConfiguredMailerService;
use App\Services\EBadgePdfService;
use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class EBadgeSendController extends Controller
{
    public function __construct(
        protected EBadgePdfService $pdfService,
        protected ConfiguredMailerService $mailerService
    ) {
    }

    public function index(Request $request)
    {
        $categories = Category::orderBy('Category')->get();
        $selectedCategory = $request->query('category');
        $search = trim((string) $request->query('search', ''));
        $selectedBadgeSize = null;

        $query = UserDetail::query();
        if ($selectedCategory) {
            $query->where('Category', $selectedCategory);
        }
        if ($search !== '') {
            $term = '%' . $search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('RegID', 'like', $term)
                    ->orWhere('Name', 'like', $term)
                    ->orWhere('Company', 'like', $term)
                    ->orWhere('Email', 'like', $term)
                    ->orWhere('Mobile', 'like', $term);
            });
        }

        $users = $query->orderBy('Name')->paginate(50)->withQueryString();
        $recentLogs = EBadgeMailLog::orderByDesc('id')->limit(20)->get();
        $setting = EBadgeSetting::getDefault();

        if ($selectedCategory) {
            $categoryModel = Category::where('Category', $selectedCategory)->first();
            if ($categoryModel) {
                $fallbackWidthPt = (float) $categoryModel->badge_width * 2.83465;
                $fallbackHeightPt = (float) $categoryModel->badge_height * 2.83465;
                $selectedBadgeSize = [
                    'width_px' => (int) round($fallbackWidthPt * (96 / 72)),
                    'height_px' => (int) round($fallbackHeightPt * (96 / 72)),
                    'source' => 'category_fallback',
                ];

                if ($categoryModel->e_badge_background_path) {
                    $bgPath = storage_path('app/public/' . $categoryModel->e_badge_background_path);
                    $imageSize = is_file($bgPath) ? @getimagesize($bgPath) : false;
                    if ($imageSize && !empty($imageSize[0]) && !empty($imageSize[1])) {
                        $selectedBadgeSize = [
                            'width_px' => (int) $imageSize[0],
                            'height_px' => (int) $imageSize[1],
                            'source' => 'background_image',
                        ];
                    }
                }
            }
        }

        return view('admin.e-badge.send.index', compact(
            'categories',
            'selectedCategory',
            'search',
            'users',
            'recentLogs',
            'setting',
            'selectedBadgeSize'
        ));
    }

    public function sendUser(Request $request)
    {
        $validated = $request->validate([
            'user_detail_id' => 'required|integer|exists:user_details,id',
            'category' => 'nullable|string',
            'search' => 'nullable|string',
        ]);

        $user = UserDetail::findOrFail($validated['user_detail_id']);
        [$ok, $message] = $this->sendBadgeToUser($user);

        return redirect()->route('admin.e-badge.send.index', [
            'category' => $validated['category'] ?? null,
            'search' => $validated['search'] ?? null,
        ])->with($ok ? 'success' : 'error', $message);
    }

    public function sendWhatsapp(Request $request)
    {
        $validated = $request->validate([
            'user_detail_id' => 'required|integer|exists:user_details,id',
            'category' => 'nullable|string',
            'search' => 'nullable|string',
        ]);

        $user = UserDetail::findOrFail($validated['user_detail_id']);
        [$ok, $message] = $this->sendWhatsappToUser($user);

        return redirect()->route('admin.e-badge.send.index', [
            'category' => $validated['category'] ?? null,
            'search' => $validated['search'] ?? null,
        ])->with($ok ? 'success' : 'error', $message);
    }

    public function previewUserPdf(int $userId)
    {
        $user = UserDetail::findOrFail($userId);
        $category = Category::where('Category', $user->Category)->first();
        if (!$category) {
            return redirect()->route('admin.e-badge.send.index')->with('error', 'Category not found for selected user.');
        }
        if (!$this->isBackgroundRenderable($category)) {
            return redirect()->route('admin.e-badge.send.index', [
                'category' => $category->Category,
            ])->with('error', 'Background image format is not supported by this server for PDF rendering. Please upload PNG background for category ' . $category->Category . '.');
        }
        $pdf = $this->pdfService->generateForUser($user);

        return response($pdf['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $pdf['filename'] . '"',
        ]);
    }

    public function sendBulk(Request $request)
    {
        $validated = $request->validate([
            'category' => 'nullable|string',
            'search' => 'nullable|string',
            'selected_user_ids' => 'nullable|array',
            'selected_user_ids.*' => 'integer|exists:user_details,id',
        ]);

        $query = UserDetail::query();
        if (!empty($validated['selected_user_ids'])) {
            $query->whereIn('id', $validated['selected_user_ids']);
        } elseif (!empty($validated['category'])) {
            $query->where('Category', $validated['category']);
            if (!empty($validated['search'])) {
                $term = '%' . $validated['search'] . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('RegID', 'like', $term)
                        ->orWhere('Name', 'like', $term)
                        ->orWhere('Company', 'like', $term)
                        ->orWhere('Email', 'like', $term)
                        ->orWhere('Mobile', 'like', $term);
                });
            }
        } else {
            return redirect()->back()->with('error', 'Please select users or choose a category first.');
        }

        $users = $query->get();
        if ($users->isEmpty()) {
            return redirect()->back()->with('error', 'No users found for sending e-badges.');
        }

        $successCount = 0;
        $failedCount = 0;
        foreach ($users as $user) {
            [$ok] = $this->sendBadgeToUser($user);
            if ($ok) {
                $successCount++;
            } else {
                $failedCount++;
            }
        }

        return redirect()->route('admin.e-badge.send.index', [
            'category' => $validated['category'] ?? null,
            'search' => $validated['search'] ?? null,
        ])->with(
            $failedCount === 0 ? 'success' : 'error',
            'E-badge email sending completed. Success: ' . $successCount . ', Failed: ' . $failedCount . '.'
        );
    }

    public function sendBulkWhatsapp(Request $request)
    {
        $validated = $request->validate([
            'category' => 'nullable|string',
            'search' => 'nullable|string',
            'selected_user_ids' => 'nullable|array',
            'selected_user_ids.*' => 'integer|exists:user_details,id',
        ]);

        $query = UserDetail::query();
        if (!empty($validated['selected_user_ids'])) {
            $query->whereIn('id', $validated['selected_user_ids']);
        } elseif (!empty($validated['category'])) {
            $query->where('Category', $validated['category']);
            if (!empty($validated['search'])) {
                $term = '%' . $validated['search'] . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('RegID', 'like', $term)
                        ->orWhere('Name', 'like', $term)
                        ->orWhere('Company', 'like', $term)
                        ->orWhere('Email', 'like', $term)
                        ->orWhere('Mobile', 'like', $term);
                });
            }
        } else {
            return redirect()->back()->with('error', 'Please select users or choose a category first.');
        }

        $users = $query->get();
        if ($users->isEmpty()) {
            return redirect()->back()->with('error', 'No users found for sending WhatsApp e-badges.');
        }

        $successCount = 0;
        $failedCount = 0;
        foreach ($users as $user) {
            [$ok] = $this->sendWhatsappToUser($user);
            if ($ok) {
                $successCount++;
            } else {
                $failedCount++;
            }
        }

        return redirect()->route('admin.e-badge.send.index', [
            'category' => $validated['category'] ?? null,
            'search' => $validated['search'] ?? null,
        ])->with(
            $failedCount === 0 ? 'success' : 'error',
            'E-badge WhatsApp sending completed. Success: ' . $successCount . ', Failed: ' . $failedCount . '.'
        );
    }

    /**
     * @return array{content:string,filename:string,storage_path:string,url:string}
     */
    protected function buildStoredPdfPayload(UserDetail $user): array
    {
        $pdf = $this->pdfService->generateForUser($user);
        $pdfStoragePath = 'e-badge-pdfs/' . ($user->RegID ?: $user->id) . '_' . now()->format('Ymd_His') . '.pdf';
        Storage::disk('public')->put($pdfStoragePath, $pdf['content']);

        return [
            'content' => $pdf['content'],
            'filename' => $pdf['filename'],
            'storage_path' => $pdfStoragePath,
            'url' => $this->publicStorageUrl($pdfStoragePath),
        ];
    }

    /**
     * @return array{0:bool,1:string}
     */
    protected function sendBadgeToUser(UserDetail $user): array
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
    protected function sendWhatsappToUser(UserDetail $user): array
    {
        $mobile = preg_replace('/\s+/', '', (string) ($user->Mobile ?? ''));
        if ($mobile === '') {
            return [false, 'Cannot send WhatsApp: mobile number missing for RegID ' . $user->RegID . '.'];
        }

        $interaktApiKey = (string) config('services.interakt.api_key');
        if ($interaktApiKey === '') {
            return [false, 'Cannot send WhatsApp: INTERAKT_API_KEY is not configured.'];
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
        $pdfUrl = $pdfPayload['url'];

        [$countryCode, $phoneNumber] = $this->splitPhoneForInterakt($mobile);
        $tableValue = trim((string) ($user->Additional1 ?? ''));
        $tableValue = $tableValue !== '' ? $tableValue : 'N/A';

        $payload = [
            'countryCode' => $countryCode,
            'phoneNumber' => $phoneNumber,
            'callbackData' => (string) config('services.interakt.callback_data', 'ambassador_meet_pdf'),
            'type' => 'Template',
            'template' => [
                'name' => (string) config('services.interakt.template_name', 'ambassador_meet'),
                'languageCode' => (string) config('services.interakt.language_code', 'en'),
                'headerValues' => [
                    $pdfUrl,
                ],
                'bodyValues' => [
                    (string) ($user->Name ?? ''),
                    $pdfUrl,
                    $tableValue,
                ],
            ],
        ];

        /** @var \Illuminate\Http\Client\Response $response */
        $response = Http::withOptions([
            'verify' => (bool) config('services.interakt.ssl_verify', true),
        ])->withHeaders([
            'Authorization' => 'Basic ' . $interaktApiKey,
            'Content-Type' => 'application/json',
        ])->post((string) config('services.interakt.base_url', 'https://api.interakt.ai/v1/public/message/'), $payload);

        if (!$response->successful()) {
            return [false, 'WhatsApp send failed for ' . $user->RegID . ': ' . $response->status() . ' ' . $response->body()];
        }

        return [true, 'WhatsApp message sent successfully to ' . $mobile . '.'];
    }

    /**
     * @return array<string,string>
     */
    protected function buildTemplateReplacements(UserDetail $user, Category $category, string $badgeDownloadLink = ''): array
    {
        $eventSettings = EventSetting::getSettings();
        $eventLogoUrl = $eventSettings->logo_path ? $this->publicStorageUrl($eventSettings->logo_path) : '';
        $emailLogoUrl = $eventSettings->email_logo_path ? $this->publicStorageUrl($eventSettings->email_logo_path) : '';
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
            '{{BadgeBackgroundUrl}}' => $category->e_badge_background_path ? $this->publicStorageUrl($category->e_badge_background_path) : '',
        ];
    }

    protected function publicStorageUrl(string $path): string
    {
        return PublicStorageUrl::make($path);
    }

    protected function isBackgroundRenderable(Category $category): bool
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

    /**
     * @return array{0:string,1:string}
     */
    protected function splitPhoneForInterakt(string $mobile): array
    {
        $defaultCountryCode = (string) config('services.interakt.country_code', '+91');
        $trimmed = trim($mobile);

        if (str_starts_with($trimmed, '+')) {
            $digits = preg_replace('/\D+/', '', $trimmed);
            if (strlen($digits) > 10) {
                $countryDigits = substr($digits, 0, strlen($digits) - 10);
                $phoneDigits = substr($digits, -10);
                return ['+' . $countryDigits, $phoneDigits];
            }
            return [$defaultCountryCode, preg_replace('/\D+/', '', $trimmed)];
        }

        $digits = preg_replace('/\D+/', '', $trimmed);
        if (strlen($digits) > 10) {
            $countryDigits = substr($digits, 0, strlen($digits) - 10);
            $phoneDigits = substr($digits, -10);
            return ['+' . $countryDigits, $phoneDigits];
        }

        return [$defaultCountryCode, $digits];
    }
}
