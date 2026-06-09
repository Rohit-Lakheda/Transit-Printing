<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\UserDetail;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RegisteredUserController extends Controller
{
    public function index(Request $request)
    {
        $categories = Category::orderBy('Category')->get();
        $selectedCategory = $request->query('category');
        $search = trim((string) $request->query('search', ''));
        $dataFrom = trim((string) $request->query('data_from', ''));

        $query = UserDetail::query()->orderByDesc('id');

        if ($selectedCategory) {
            $query->where('Category', $selectedCategory);
        }

        if ($dataFrom !== '') {
            $query->where('DataFrom', $dataFrom);
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

        $users = $query->paginate(50)->withQueryString();
        $dataFromOptions = UserDetail::query()
            ->whereNotNull('DataFrom')
            ->where('DataFrom', '!=', '')
            ->distinct()
            ->orderBy('DataFrom')
            ->pluck('DataFrom');

        return view('admin.registered-users.index', compact(
            'categories',
            'selectedCategory',
            'search',
            'dataFrom',
            'dataFromOptions',
            'users'
        ));
    }

    public function edit(UserDetail $registeredUser)
    {
        $categories = Category::orderBy('Category')->get();

        return view('admin.registered-users.edit', [
            'user' => $registeredUser,
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, UserDetail $registeredUser)
    {
        $validated = $request->validate([
            'RegID' => [
                'required',
                'string',
                'max:255',
                Rule::unique('user_details', 'RegID')->ignore($registeredUser->id),
            ],
            'Category' => 'required|string|exists:categories,Category',
            'Name' => 'required|string|max:255',
            'Designation' => 'nullable|string|max:255',
            'Company' => 'nullable|string|max:255',
            'Country' => 'nullable|string|max:255',
            'State' => 'nullable|string|max:255',
            'City' => 'nullable|string|max:255',
            'Email' => 'nullable|email|max:255',
            'Mobile' => 'nullable|string|max:50',
            'ReceiptNumber' => 'nullable|string|max:255',
            'Additional1' => 'nullable|string|max:255',
            'Additional2' => 'nullable|string|max:255',
            'Additional3' => 'nullable|string|max:255',
            'Additional4' => 'nullable|string|max:255',
            'Additional5' => 'nullable|string|max:255',
            'IsLunchAllowed' => 'nullable|boolean',
            'DataFrom' => 'nullable|string|max:255',
            'client_registration_id' => 'nullable|string|max:255',
        ]);

        $validated['IsLunchAllowed'] = $request->boolean('IsLunchAllowed');

        $registeredUser->update($validated);

        return redirect()
            ->route('admin.registered-users.edit', $registeredUser)
            ->with('success', 'Registration updated successfully.');
    }
}
