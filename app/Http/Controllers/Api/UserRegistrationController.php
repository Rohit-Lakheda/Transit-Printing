<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserDetail;
use App\Models\Category;
use App\Models\ApiConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\EBadgeDispatchService;
use App\Services\RegIdGenerator;

class UserRegistrationController extends Controller
{
    public function __construct(
        protected EBadgeDispatchService $ebadgeDispatch
    ) {
    }

    /**
     * Register user via API
     */
    public function register(Request $request, $apiKey)
    {
        // Find API configuration
        $apiConfig = ApiConfiguration::where('api_key', $apiKey)
            ->where('is_active', true)
            ->first();

        if (!$apiConfig) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or inactive API key'
            ], 401);
        }

        // Get field mappings or use default
        $fieldMappings = $apiConfig->field_mappings;
        if (empty($fieldMappings) || !is_array($fieldMappings)) {
            $fieldMappings = $this->getDefaultFieldMappings();
        }
        
        // Map incoming data to database columns
        $mappedData = [];
        foreach ($fieldMappings as $apiField => $dbColumn) {
            if ($request->has($apiField)) {
                $value = $request->input($apiField);
                
                // Convert IsLunchAllowed to boolean if present
                if ($dbColumn === 'IsLunchAllowed') {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                }
                
                $mappedData[$dbColumn] = $value;
            }
        }

        $categoryExists = Category::where('Category', $mappedData['Category'] ?? '')->exists();

        $validator = Validator::make($mappedData, [
            'Category' => 'required|string',
            'Name' => 'required|string|max:255',
        ]);

        if ($validator->fails() || !$categoryExists) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()->merge(
                    !$categoryExists ? ['Category' => ['Invalid category for this event']] : []
                ),
            ], 422);
        }

        // Handle RegID - generate if not provided
        if (empty($mappedData['RegID'])) {
            $mappedData['RegID'] = RegIdGenerator::generateForCategory($mappedData['Category']);
        } else {
            if (UserDetail::where('RegID', $mappedData['RegID'])->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'RegID already exists',
                    'regid' => $mappedData['RegID']
                ], 409);
            }
        }

        // Set DataFrom
        $mappedData['DataFrom'] = 'Through API';
        $mappedData['Data_Received_At'] = now();

        // Create user
        try {
            $user = UserDetail::create($mappedData);
            $ebadgeDispatch = $this->ebadgeDispatch->sendOnApiRegistration($user);

            return response()->json([
                'success' => true,
                'message' => 'User registered successfully',
                'data' => [
                    'id' => $user->id,
                    'RegID' => $user->RegID,
                    'Name' => $user->Name,
                    'Category' => $user->Category,
                ],
                'ebadge_dispatch' => $ebadgeDispatch,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to register user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get default field mappings
     */
    private function getDefaultFieldMappings()
    {
        return [
            'regid' => 'RegID',
            'category' => 'Category',
            'name' => 'Name',
            'designation' => 'Designation',
            'company' => 'Company',
            'country' => 'Country',
            'state' => 'State',
            'city' => 'City',
            'email' => 'Email',
            'mobile' => 'Mobile',
            'additional1' => 'Additional1',
            'additional2' => 'Additional2',
            'additional3' => 'Additional3',
            'additional4' => 'Additional4',
            'additional5' => 'Additional5',
            'receipt_number' => 'ReceiptNumber',
            'is_lunch_allowed' => 'IsLunchAllowed',
        ];
    }
}
