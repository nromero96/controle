<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Branch;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class BranchController extends Controller
{
    public function index()
    {
        $data = [
            'category_name' => 'branches',
            'page_name' => 'branches.index',
            'page_title' => 'Sedes',
        ];

        $branches = Branch::withCount('employees')->orderBy('name')->get();

        return view('pages.branches.index')
            ->with('branches', $branches)
            ->with('data', $data);
    }

    public function create()
    {
        $data = [
            'category_name' => 'branches',
            'page_name' => 'branches.create',
            'page_title' => 'Crear Sede',
        ];

        return view('pages.branches.create')->with('data', $data);
    }

    public function store(Request $request)
    {
        Branch::create($this->validatedData($request));

        return redirect()->route('branches.index')
            ->with('success', 'Sede creada exitosamente.');
    }

    public function show($id)
    {
        // Logic to show a specific branch
    }

    public function edit($id)
    {
        $branch = Branch::findOrFail($id);

        $data = [
            'category_name' => 'branches',
            'page_name' => 'branches.edit',
            'page_title' => 'Editar Sede',
        ];

        return view('pages.branches.edit')
            ->with('branch', $branch)
            ->with('data', $data);
    }

    public function update(Request $request, $id)
    {
        $branch = Branch::findOrFail($id);
        $branch->update($this->validatedData($request, $branch));

        return redirect()->route('branches.index')
            ->with('success', 'Sede actualizada exitosamente.');
    }

    public function destroy($id)
    {
        try {
            $deleted = DB::transaction(function () use ($id) {
                $branch = Branch::lockForUpdate()->findOrFail($id);
                if ($branch->employees()->exists()) {
                    return false;
                }
                return $branch->delete();
            });
        } catch (QueryException $e) {
            if (!in_array((string) $e->getCode(), ['23000', '23503'], true)) {
                throw $e;
            }
            $deleted = false;
        }

        if (!$deleted) {
            return redirect()->route('branches.index')
                ->with('error', 'Esta sede tiene empleados asignados. Puedes desactivarla, pero no eliminarla.');
        }

        return redirect()->route('branches.index')
            ->with('success', 'Sede eliminada exitosamente.');
    }

    private function validatedData(Request $request, ?Branch $branch = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('branches', 'name')->ignore($branch?->id)],
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9\s().-]+$/', 'regex:/[0-9]/'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ], [
            'phone.regex' => 'Ingrese un teléfono válido: números, espacios, +, paréntesis, puntos o guiones.',
        ]);
    }
}
