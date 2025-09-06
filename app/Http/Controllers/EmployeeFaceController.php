<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Rules\FaceDescriptor;
use Illuminate\Http\Request;

class EmployeeFaceController extends Controller
{
    public function show(Employee $employee)
    {
        return view('employees.capture-face', compact('employee'));
    }

    public function store(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'face_descriptor' => ['required', new FaceDescriptor],
        ]);

        // Acepta string JSON o array:
        $descriptor = is_string($data['face_descriptor'])
            ? json_decode($data['face_descriptor'], true)
            : $data['face_descriptor'];

        $employee->update(['face_descriptor' => $descriptor]);

        return response()->json([
            'success' => true,
            'message' => 'Descriptor guardado correctamente'
        ]);
    }
}
