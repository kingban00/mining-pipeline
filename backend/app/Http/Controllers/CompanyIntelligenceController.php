<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Jobs\ProcessCompanyIntelligenceJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CompanyIntelligenceController extends Controller
{
    /**
     * Receives a comma-delimited string of company names, validates limits, 
     * and dispatches them to the queue concurrently.
     */
    public function process(Request $request): JsonResponse
    {
        // 1. Initial validation
        $request->validate([
            'companies' => 'required|string',
        ]);

        // 2. Clean, filter empty, and normalize (Title Case + Trim)
        $inputNames = explode(',', $request->input('companies'));
        $normalizedNames = array_map(function ($name) {
            return trim(mb_convert_case($name, MB_CASE_TITLE, "UTF-8"));
        }, $inputNames);

        // Deduplicate after normalization
        $names = array_unique(array_filter($normalizedNames));

        // 3. Edge Case: Check if array is empty after filtering
        if (empty($names)) {
            return response()->json(['message' => 'No valid company names provided.'], 400);
        }

        // 4. Batch Limit Rule: Prevent server overload
        if (count($names) > 10) {
            return response()->json([
                'message' => 'Please limit your request to a maximum of 10 companies to ensure processing stability.'
            ], 422);
        }

        // 5. Dispatch jobs for concurrent processing
        foreach ($names as $name) {
            ProcessCompanyIntelligenceJob::dispatch($name);
        }

        // 6. Return Accepted response
        return response()->json([
            'message' => 'Processing started successfully in the background.',
            'companies_queued' => count($names)
        ], 202);
    }

    /**
     * Returns the number of pending jobs in the mining queue.
     */
    public function status(): JsonResponse
    {
        $queueCount = \Illuminate\Support\Facades\DB::table('jobs')
            ->where('queue', 'mining')
            ->count();

        return response()->json([
            'is_processing' => $queueCount > 0,
            'pending_jobs' => $queueCount
        ]);
    }

    /**
     * Returns a paginated list of processed companies, optionally filtered by search.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Company::query()->where('status', 'completed');

        // Implement search functionality if the parameter is present
        // Using 'ilike' for case-insensitive search in PostgreSQL (Supabase)
        if ($request->has('search') && $request->filled('search')) {
            $searchTerm = $request->input('search');
            $query->where('name', 'ilike', '%' . $searchTerm . '%');
        }

        // Paginate results to 10 items per page
        $companies = $query->orderBy('name')->paginate(10);

        return response()->json($companies);
    }

    /**
     * Returns a specific company with its structured leadership team and mining assets.
     */
    public function show(string $id): JsonResponse
    {
        // Eager load the relationships to prevent N+1 queries.
        // findOrFail will automatically return a 404 if the UUID does not exist.
        $company = Company::with(['executives', 'assets'])->findOrFail($id);

        return response()->json($company);
    }
}