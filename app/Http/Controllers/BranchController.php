<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Branch;
use Illuminate\Support\Facades\Validator;

class BranchController extends Controller
{
    public function index()
    {
        $data = [
            'category_name' => 'branches',
            'page_name' => 'branches.index',
            'page_title' => 'Sedes',
        ];

        $branches = Branch::all();

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
        $validator = Validator::make($request->all(), [
            'name' => 'required|unique:branches,name|max:255',
            'address' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        Branch::create($request->all());

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
        $validator = Validator::make($request->all(), [
            'name' => 'required|unique:branches,name,' . $id . '|max:255',
            'address' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $branch = Branch::findOrFail($id);
        $branch->update($request->all());

        return redirect()->route('branches.index')
            ->with('success', 'Sede actualizada exitosamente.');
    }

    public function destroy($id)
    {
        $branch = Branch::findOrFail($id);
        $branch->delete();

        return redirect()->route('branches.index')
            ->with('success', 'Sede eliminada exitosamente.');
    }
}
