<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\EventSetting;
use App\Models\MailConfiguration;
use App\Models\LeadSetting;
use App\Models\UserCredential;
use App\Models\UserDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class LeadSettingsController extends Controller
{
    protected function resolveLeadUserRedirectRoute(string $context): string
    {
        return $context === 'users' ? 'admin.leads.users' : 'admin.leads.settings';
    }

    protected function activeMailConfigurationOrFallback(): ?MailConfiguration
    {
        return MailConfiguration::where('is_active', true)->orderByDesc('id')->first()
            ?? MailConfiguration::orderByDesc('id')->first();
    }

    public function index(Request $request)
    {
        $categories = Category::all();
        $mailConfigs = MailConfiguration::orderByDesc('id')->get();
        $activeMailConfig = $mailConfigs->first();
        $leadSetting = LeadSetting::getDefault();

        $selectedCategory = $request->query('category');
        $search = $request->query('search');

        $users = collect();
        if ($selectedCategory) {
            $query = UserDetail::where('Category', $selectedCategory);

            if ($search) {
                $term = '%' . $search . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('RegID', 'like', $term)
                        ->orWhere('Name', 'like', $term)
                        ->orWhere('Company', 'like', $term)
                        ->orWhere('Email', 'like', $term)
                        ->orWhere('Mobile', 'like', $term)
                        ->orWhere('Designation', 'like', $term);
                });
            }

            $users = $query->orderBy('Name')->limit(500)->get();
            if ($users->isNotEmpty()) {
                $credentialByUserId = UserCredential::whereIn('user_detail_id', $users->pluck('id'))
                    ->get()
                    ->keyBy('user_detail_id');

                $users = $users->map(function (UserDetail $user) use ($credentialByUserId) {
                    $credential = $credentialByUserId->get($user->id);
                    $user->setAttribute('has_credential', (bool) $credential);
                    $user->setAttribute('current_max_leads', $credential ? $credential->max_leads : null);
                    return $user;
                });
            }
        }

        return view('admin.leads.settings', compact(
            'categories',
            'mailConfigs',
            'leadSetting',
            'selectedCategory',
            'search',
            'users',
            'activeMailConfig'
        ));
    }

    public function users(Request $request)
    {
        $categories = Category::orderBy('Category')->get();
        $selectedCategory = $request->query('category');
        $search = trim((string) $request->query('search', ''));
        $credentialStatus = $request->query('credential_status', 'all');

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
                    ->orWhere('Mobile', 'like', $term)
                    ->orWhere('Designation', 'like', $term);
            });
        }

        if ($credentialStatus === 'with') {
            $query->whereIn('id', UserCredential::select('user_detail_id'));
        } elseif ($credentialStatus === 'without') {
            $query->whereNotIn('id', UserCredential::select('user_detail_id'));
        }

        $users = $query
            ->orderBy('Name')
            ->paginate(50)
            ->withQueryString();

        $credentialByUserId = collect();
        if ($users->count() > 0) {
            $credentialByUserId = UserCredential::whereIn('user_detail_id', $users->pluck('id'))
                ->get()
                ->keyBy('user_detail_id');
        }

        $users->getCollection()->transform(function (UserDetail $user) use ($credentialByUserId) {
            $credential = $credentialByUserId->get($user->id);
            $user->setAttribute('has_credential', (bool) $credential);
            $user->setAttribute('credential_username', $credential?->username);
            $user->setAttribute('credential_active', $credential ? (bool) $credential->is_active : false);
            $user->setAttribute('current_max_leads', $credential?->max_leads);
            return $user;
        });

        return view('admin.leads.users', compact(
            'categories',
            'users',
            'selectedCategory',
            'search',
            'credentialStatus'
        ));
    }

    public function saveMailConfig(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'host' => 'required|string|max:255',
            'port' => 'required|integer',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'encryption' => 'nullable|string|max:10',
            'from_address' => 'nullable|email',
            'from_name' => 'nullable|string|max:255',
            // Flags are handled manually as booleans from checkboxes
            'use_auth' => 'nullable',
            'is_active' => 'nullable',
        ]);

        $data['use_auth'] = $request->has('use_auth') ? (bool) $request->input('use_auth') : true;
        $data['is_active'] = $request->has('is_active') ? (bool) $request->input('is_active') : true;

        $config = MailConfiguration::where('name', $data['name'])->first();
        if ($config) {
            // If password field left blank, keep existing password
            if (empty($data['password'])) {
                unset($data['password']);
            }
            $config->update($data);
        } else {
            MailConfiguration::create($data);
        }

        return redirect()->route('admin.leads.settings')->with('success', 'Mail configuration saved.');
    }

    public function generateAndSendCredentials(Request $request)
    {
        $validated = $request->validate([
            'category' => 'required|string',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer',
            'max_devices' => 'nullable|integer|min:1',
            'max_leads' => 'nullable|integer|min:1',
            'mail_configuration_id' => 'required|exists:mail_configurations,id',
        ]);

        $query = UserDetail::where('Category', $validated['category']);
        if (!empty($validated['user_ids'])) {
            $query->whereIn('id', $validated['user_ids']);
        }

        $users = $query->get();
        if ($users->isEmpty()) {
            return redirect()->back()->with('error', 'No users found for selected criteria.');
        }

        $mailConfig = MailConfiguration::findOrFail($validated['mail_configuration_id']);
        $leadSetting = LeadSetting::getDefault();

        foreach ($users as $user) {
            $credential = UserCredential::firstOrNew([
                'user_detail_id' => $user->id,
            ]);

            $rawPassword = strtoupper(substr((string) $user->RegID, -4)) . random_int(1000, 9999);

            if (!$credential->exists) {
                $credential->username = $user->Email ?: $user->RegID;
            }
            if (array_key_exists('max_devices', $validated)) {
                $credential->max_devices = $validated['max_devices'];
            }
            if (array_key_exists('max_leads', $validated)) {
                $credential->max_leads = $validated['max_leads'];
            }
            // Always rotate password when admin sends credentials again.
            $credential->password = Hash::make($rawPassword);
            $credential->remember_token = null;
            $credential->is_active = true;
            $credential->save();

            if (!$user->Email) {
                continue;
            }

            $this->sendCredentialMail($user, $credential, $rawPassword, $mailConfig, $leadSetting);
        }

        return redirect()->route('admin.leads.settings')->with('success', 'Credentials regenerated and emails dispatched where possible.');
    }

    protected function sendCredentialMail(UserDetail $user, UserCredential $credential, ?string $rawPassword, MailConfiguration $config, LeadSetting $leadSetting): void
    {
        $symfonyMailer = new \Symfony\Component\Mailer\Mailer(
            \App\Services\ConfiguredMailerService::createTransport($config)
        );

        $fromAddress = $config->from_address ?: config('mail.from.address');
        $fromName = $config->from_name ?: config('mail.from.name');

        $subject = $leadSetting->credential_email_subject ?: 'Your Event Access Credentials';

        $bodyTemplate = $leadSetting->credential_email_body;
        if (!$bodyTemplate) {
            $bodyTemplate = <<<HTML
{{EmailLogoImage}}
<p>Dear {{Name}},</p>
<p>Here are your access credentials:</p>
<ul>
    <li><strong>Username:</strong> {{Username}}</li>
    <li><strong>Password:</strong> {{Password}}</li>
</ul>
<p>Max devices: {{MaxDevices}}</p>
<p>Lead generation limit: {{MaxLeads}}</p>
HTML;
        }

        $maxDevicesText = $credential->max_devices ? (string) $credential->max_devices : 'Unlimited';
        $maxLeadsText = $credential->max_leads ? (string) $credential->max_leads : 'Unlimited';
        $passwordText = $rawPassword ?: '(unchanged)';

        $leadPortalUrl = url('/lead/login');
        $resetPageUrl = url('/lead/forgot-password');
        $eventSettings = EventSetting::getSettings();
        $emailLogoUrl = $eventSettings->email_logo_path
            ? \App\Support\PublicStorageUrl::make($eventSettings->email_logo_path)
            : '';
        $emailLogoHtml = $emailLogoUrl !== ''
            ? '<img src="' . e($emailLogoUrl) . '" alt="Email Logo" style="max-width:220px;height:auto;">'
            : '';

        $replacements = [
            '{{Name}}' => $user->Name ?? '',
            '{{Company}}' => $user->Company ?? '',
            '{{Category}}' => $user->Category ?? '',
            '{{RegID}}' => $user->RegID ?? '',
            '{{Email}}' => $user->Email ?? '',
            '{{Mobile}}' => $user->Mobile ?? '',
            '{{Username}}' => $credential->username,
            '{{Password}}' => $passwordText,
            '{{MaxDevices}}' => $maxDevicesText,
            '{{MaxLeads}}' => $maxLeadsText,
            '{{LeadLink}}' => $leadPortalUrl,
            '{{ResetPasswordLink}}' => $resetPageUrl,
            '{{EmailLogoUrl}}' => $emailLogoUrl,
            '{{EmailLogoImage}}' => $emailLogoHtml,
        ];

        $renderedBody = str_replace(array_keys($replacements), array_values($replacements), $bodyTemplate);

        $message = (new \Symfony\Component\Mime\Email())
            ->from(new \Symfony\Component\Mime\Address($fromAddress, $fromName))
            ->to($user->Email)
            ->subject($subject)
            ->html($renderedBody);

        $symfonyMailer->send($message);
    }

    public function saveLeadShareSettings(Request $request)
    {
        $data = $request->validate([
            'share_RegID' => 'nullable|boolean',
            'share_Name' => 'nullable|boolean',
            'share_Category' => 'nullable|boolean',
            'share_Company' => 'nullable|boolean',
            'share_Email' => 'nullable|boolean',
            'share_Mobile' => 'nullable|boolean',
            'share_Designation' => 'nullable|boolean',
            'share_Country' => 'nullable|boolean',
            'share_State' => 'nullable|boolean',
            'share_City' => 'nullable|boolean',
            'share_Additional1' => 'nullable|boolean',
            'share_Additional2' => 'nullable|boolean',
            'share_Additional3' => 'nullable|boolean',
            'share_Additional4' => 'nullable|boolean',
            'share_Additional5' => 'nullable|boolean',
            'credential_email_subject' => 'nullable|string|max:255',
            'credential_email_body' => 'nullable|string',
        ]);

        $leadSetting = LeadSetting::getDefault();

        foreach ($leadSetting->getFillable() as $field) {
            if (str_starts_with($field, 'share_')) {
                $leadSetting->{$field} = isset($data[$field]) ? (bool) $data[$field] : false;
            }
        }

        if (array_key_exists('credential_email_subject', $data)) {
            $leadSetting->credential_email_subject = $data['credential_email_subject'];
        }
        if (array_key_exists('credential_email_body', $data)) {
            $leadSetting->credential_email_body = $data['credential_email_body'];
        }

        $leadSetting->save();

        return redirect()->back()->with('success', 'Lead sharing and email template settings saved.');
    }

    public function updateUserLeadLimit(Request $request)
    {
        $validated = $request->validate([
            'user_detail_id' => 'required|integer|exists:user_details,id',
            'max_leads' => 'nullable|integer|min:1',
            'category' => 'nullable|string',
            'search' => 'nullable|string',
            'credential_status' => 'nullable|string',
            'context' => 'nullable|string',
        ]);

        $routeName = $this->resolveLeadUserRedirectRoute($validated['context'] ?? 'settings');

        $credential = UserCredential::where('user_detail_id', $validated['user_detail_id'])->first();
        if (!$credential) {
            return redirect()
                ->route($routeName, [
                    'category' => $validated['category'] ?? null,
                    'search' => $validated['search'] ?? null,
                    'credential_status' => $validated['credential_status'] ?? null,
                ])
                ->with('error', 'Credentials are not generated for this user yet. Please generate credentials first.');
        }

        $credential->max_leads = $validated['max_leads'] ?? null;
        $credential->save();

        $message = is_null($credential->max_leads)
            ? 'Lead limit removed for selected user (now unlimited).'
            : 'Lead limit updated for selected user.';

        return redirect()
            ->route($routeName, [
                'category' => $validated['category'] ?? null,
                'search' => $validated['search'] ?? null,
                'credential_status' => $validated['credential_status'] ?? null,
            ])
            ->with('success', $message);
    }

    public function sendUserCredentials(Request $request)
    {
        $validated = $request->validate([
            'user_detail_id' => 'required|integer|exists:user_details,id',
            'category' => 'nullable|string',
            'search' => 'nullable|string',
            'credential_status' => 'nullable|string',
            'context' => 'nullable|string',
        ]);

        $routeName = $this->resolveLeadUserRedirectRoute($validated['context'] ?? 'users');

        $user = UserDetail::findOrFail($validated['user_detail_id']);
        if (!$user->Email) {
            return redirect()
                ->route($routeName, [
                    'category' => $validated['category'] ?? null,
                    'search' => $validated['search'] ?? null,
                    'credential_status' => $validated['credential_status'] ?? null,
                ])
                ->with('error', 'Cannot send credentials because this user does not have an email address.');
        }

        $mailConfig = $this->activeMailConfigurationOrFallback();
        if (!$mailConfig) {
            return redirect()
                ->route($routeName, [
                    'category' => $validated['category'] ?? null,
                    'search' => $validated['search'] ?? null,
                    'credential_status' => $validated['credential_status'] ?? null,
                ])
                ->with('error', 'No mail configuration found. Please configure mail settings first.');
        }

        $leadSetting = LeadSetting::getDefault();
        $credential = UserCredential::firstOrNew([
            'user_detail_id' => $user->id,
        ]);

        if (!$credential->exists) {
            $credential->username = $user->Email ?: $user->RegID;
        }

        $rawPassword = strtoupper(substr((string) $user->RegID, -4)) . random_int(1000, 9999);
        $credential->password = Hash::make($rawPassword);
        $credential->remember_token = null;
        $credential->is_active = true;
        $credential->save();

        $this->sendCredentialMail($user, $credential, $rawPassword, $mailConfig, $leadSetting);

        return redirect()
            ->route($routeName, [
                'category' => $validated['category'] ?? null,
                'search' => $validated['search'] ?? null,
                'credential_status' => $validated['credential_status'] ?? null,
            ])
            ->with('success', 'Credentials sent successfully to ' . ($user->Email ?? 'selected user') . '.');
    }

    public function shareIndex()
    {
        $leadSetting = LeadSetting::getDefault();
        return view('admin.leads.share', compact('leadSetting'));
    }
}

