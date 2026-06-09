<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\EBadgeMailLog;
use App\Models\EBadgeSetting;
use App\Models\UserDetail;
use App\Services\EBadgeDispatchService;
use App\Services\EBadgePdfService;
use Illuminate\Http\Request;

class EBadgeSendController extends Controller
{
    public function __construct(
        protected EBadgePdfService $pdfService,
        protected EBadgeDispatchService $dispatchService
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
        [$ok, $message] = $this->dispatchService->sendEmailToUser($user);

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
        [$ok, $message] = $this->dispatchService->sendWhatsappToUser($user);

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
        if (!$this->dispatchService->isBackgroundRenderable($category)) {
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

        $users = $this->resolveUsersForBulk($validated);
        if ($users === null) {
            return redirect()->back()->with('error', 'Please select users or choose a category first.');
        }
        if ($users->isEmpty()) {
            return redirect()->back()->with('error', 'No users found for sending e-badges.');
        }

        $successCount = 0;
        $failedCount = 0;
        foreach ($users as $user) {
            [$ok] = $this->dispatchService->sendEmailToUser($user);
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

        $users = $this->resolveUsersForBulk($validated);
        if ($users === null) {
            return redirect()->back()->with('error', 'Please select users or choose a category first.');
        }
        if ($users->isEmpty()) {
            return redirect()->back()->with('error', 'No users found for sending WhatsApp e-badges.');
        }

        $successCount = 0;
        $failedCount = 0;
        foreach ($users as $user) {
            [$ok] = $this->dispatchService->sendWhatsappToUser($user);
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
     * @param  array<string, mixed>  $validated
     */
    protected function resolveUsersForBulk(array $validated)
    {
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
            return null;
        }

        return $query->get();
    }
}
