<?php

namespace App\Http\Controllers\Newsletter;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Newsletter\Template;
use Inertia\Inertia;
use Inertia\Response;

class TemplateController extends Controller
{
    /**
     * Display a listing of templates.
     */
    public function index(Request $request): Response|\Illuminate\Http\JsonResponse
    {
        $templates = Template::query()
            ->latest()
            ->paginate(20);

        if ($request->wantsJson()) {
            return response()->json($templates);
        }

        return Inertia::render('Newsletter/Templates/Index', [
            'templates' => $templates,
        ]);
    }

    /**
     * Show create template form
     */
    public function create(Request $request): Response
    {
        // Provide a few templates to load into the builder quickly
        $templates = Template::query()->latest()->limit(20)->get(['id', 'name', 'description', 'content', 'html_content']);

        return Inertia::render('Newsletter/Templates/Create', [
            'templates' => $templates,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'content' => ['required'], // JSON structure (array or JSON string)
            'html_content' => ['required', 'string'],
            'thumbnail' => ['nullable', 'string'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        // Ensure content is array (supports raw JSON string input)
        if (is_string($validated['content'])) {
            $decoded = json_decode($validated['content'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $validated['content'] = $decoded;
            }
        }

        $template = Template::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'content' => $validated['content'],
            'html_content' => $validated['html_content'],
            'thumbnail' => $validated['thumbnail'] ?? null,
            'is_default' => (bool) ($validated['is_default'] ?? false),
            'created_by' => optional($request->user())->id,
        ]);

        // If caller wants to enforce default, use model helper to ensure exclusivity
        if ($request->boolean('make_default') || $template->is_default) {
            $template->makeDefault();
        }

        if ($request->wantsJson()) {
            return response()->json(['data' => $template], 201);
        }

        return redirect()
            ->route('newsletter.templates.edit', $template->id)
            ->with('success', 'Template created successfully.');
    }

    /**
     * Display a single template.
     */
    public function show(Template $template, Request $request)
    {
        if ($request->wantsJson()) {
            return response()->json(['data' => $template]);
        }

        // Redirect to edit page as the primary UI for templates
        return redirect()->route('newsletter.templates.edit', $template->id);
    }

    /**
     * Show edit template form
     */
    public function edit(Template $template): Response
    {
        $templates = Template::query()->latest()->limit(20)->get(['id', 'name', 'description', 'content', 'html_content']);

        return Inertia::render('Newsletter/Templates/Edit', [
            'template' => $template,
            'templates' => $templates,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Template $template)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'content' => ['sometimes'], // JSON structure (array or JSON string)
            'html_content' => ['sometimes', 'string'],
            'thumbnail' => ['nullable', 'string'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('content', $validated) && is_string($validated['content'])) {
            $decoded = json_decode($validated['content'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $validated['content'] = $decoded;
            }
        }

        $template->fill($validated);
        $template->save();

        if ($request->boolean('make_default') || ($validated['is_default'] ?? false)) {
            $template->makeDefault();
        }

        if ($request->wantsJson()) {
            return response()->json(['data' => $template]);
        }

        return redirect()->route('newsletter.templates.edit', $template->id)
            ->with('success', 'Template updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Template $template, Request $request)
    {
        $template->delete();

        if ($request->wantsJson()) {
            return response()->json(['status' => 'deleted']);
        }

        return redirect()->back()->with('success', 'Template deleted successfully.');
    }
}
