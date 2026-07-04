<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Document\StoreCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    // ─── GET /api/categories ──────────────────────────────────────

    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $query = Category::withCount('documents');

        if ($request->boolean('tree')) {
            // Return root categories with children loaded (non-paginated tree structure)
            $categories = $query->whereNull('parent_id')
                ->with('children')
                ->orderBy('name')
                ->get();

            return response()->json([
                'data' => CategoryResource::collection($categories),
            ]);
        }

        $categories = $query->with('parent')
            ->orderBy('name')
            ->paginate(50);

        return CategoryResource::collection($categories);
    }

    // ─── POST /api/categories ─────────────────────────────────────

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', Category::class);

        $category = Category::create([
            'name'        => $request->validated('name'),
            'slug'        => $request->validated('slug'),
            'description' => $request->validated('description'),
            'parent_id'   => $request->validated('parent_id'),
            'created_by'  => $request->user()->id,
        ]);

        return response()->json([
            'message'  => 'สร้างหมวดหมู่สำเร็จ',
            'category' => new CategoryResource($category),
        ], 201);
    }

    // ─── GET /api/categories/{category} ──────────────────────────

    public function show(Category $category): CategoryResource
    {
        $category->load(['parent', 'children'])->loadCount('documents');
        return new CategoryResource($category);
    }

    // ─── PUT /api/categories/{category} ──────────────────────────

    public function update(StoreCategoryRequest $request, Category $category): JsonResponse
    {
        $this->authorize('update', $category);

        $category->update($request->validated());

        return response()->json([
            'message'  => 'อัปเดตหมวดหมู่สำเร็จ',
            'category' => new CategoryResource($category->fresh(['parent', 'children'])),
        ]);
    }

    // ─── DELETE /api/categories/{category} ───────────────────────

    public function destroy(Category $category): JsonResponse
    {
        $this->authorize('delete', $category);

        if ($category->children()->exists()) {
            return response()->json([
                'message' => 'ไม่สามารถลบหมวดหมู่ที่มีหมวดหมู่ย่อยได้',
            ], 422);
        }

        if ($category->documents()->exists()) {
            return response()->json([
                'message' => 'ไม่สามารถลบหมวดหมู่ที่มีเอกสารอยู่ได้',
            ], 422);
        }

        $category->delete();

        return response()->json(['message' => 'ลบหมวดหมู่สำเร็จ']);
    }
}
