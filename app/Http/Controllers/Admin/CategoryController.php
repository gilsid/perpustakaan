<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use App\Services\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class CategoryController extends Controller
{
    public function __construct(private readonly Audit $audit) {}

    public function index(): View
    {
        return view('admin.categories', [
            'nav' => 'categories',
            'categories' => Category::query()->withCount('books')->orderBy('name')->get(),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $category = Category::create($request->validated());
        $this->audit->categoryCreated($category, $request->user());

        return redirect()->route('admin.categories.index')->with('success', "Kategori \"{$category->name}\" ditambahkan.");
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $old = $category->name;
        $category->update($request->validated());
        $this->audit->categoryUpdated($category, $old, $request->user());

        return redirect()->route('admin.categories.index')->with('success', 'Kategori diperbarui.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $count = $category->books()->count();
        if ($count > 0) {
            return redirect()->route('admin.categories.index')
                ->with('error', "Kategori \"{$category->name}\" masih dipakai $count buku. Pindahkan bukunya ke kategori lain dulu.");
        }

        $category->delete();
        $this->audit->categoryDeleted($category, request()->user());

        return redirect()->route('admin.categories.index')->with('success', 'Kategori dihapus.');
    }
}
