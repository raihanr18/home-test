<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchStudentsRequest;
use App\Http\Resources\StudentResource;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StudentController extends Controller
{
    /**
     * Display a paginated listing of students with optional search and sorting.
     *
     * @param SearchStudentsRequest $request
     * @return AnonymousResourceCollection
     */
    public function index(SearchStudentsRequest $request): AnonymousResourceCollection
    {
        $query = Student::query();

        // Apply search filter (case-insensitive partial match on nama or nim)
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                // Use database-agnostic case-insensitive search
                if (config('database.default') === 'pgsql') {
                    $q->where('nama', 'ILIKE', "%{$search}%")
                      ->orWhere('nim', 'LIKE', "%{$search}%");
                } else {
                    // SQLite and others - use LOWER() for case-insensitive search
                    $q->whereRaw('LOWER(nama) LIKE ?', ['%' . strtolower($search) . '%'])
                      ->orWhere('nim', 'LIKE', "%{$search}%");
                }
            });
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'nama');
        $sortOrder = $request->input('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginate results
        $perPage = $request->input('per_page', 15);
        $students = $query->paginate($perPage);

        return StudentResource::collection($students);
    }

    /**
     * Display the specified student by NIM.
     *
     * @param string $nim
     * @return StudentResource
     */
    public function show(string $nim): StudentResource
    {
        $student = Student::where('nim', $nim)->firstOrFail();
        
        return new StudentResource($student);
    }

    /**
     * Remove the specified student from storage (soft delete).
     *
     * @param string $nim
     * @return JsonResponse
     */
    public function destroy(string $nim): JsonResponse
    {
        $student = Student::where('nim', $nim)->firstOrFail();
        $student->delete();

        return response()->json(null, 204);
    }
}
