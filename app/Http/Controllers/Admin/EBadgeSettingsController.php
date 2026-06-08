<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EBadgeSetting;
use App\Models\MailConfiguration;
use Illuminate\Http\Request;

class EBadgeSettingsController extends Controller
{
    public function index()
    {
        $setting = EBadgeSetting::getDefault();
        $mailConfigurations = MailConfiguration::orderByDesc('id')->get();

        return view('admin.e-badge.settings', compact('setting', 'mailConfigurations'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'email_subject' => 'required|string|max:255',
            'email_body' => 'required|string',
            'mail_configuration_id' => 'nullable|exists:mail_configurations,id',
        ]);

        $setting = EBadgeSetting::getDefault();
        $setting->email_subject = $validated['email_subject'];
        $setting->email_body = $validated['email_body'];
        $setting->mail_configuration_id = $validated['mail_configuration_id'] ?? null;
        $setting->save();

        return redirect()->route('admin.e-badge.settings')->with('success', 'E-badge email settings saved.');
    }
}
