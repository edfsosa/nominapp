<?php

namespace App\Http\Controllers;

use App\Models\CheckIn;
use App\Models\Employee;
use Illuminate\Http\Request;

class CheckInController extends Controller
{
    public function showForm()
    {
        return view('checkin.form');
    }

    public function store(Request $request)
    {
        $request->validate([
            'ci' => 'required|exists:employees,ci',
            'type' => 'required|in:entrada,salida',
            'photo' => 'required|image',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $employee = Employee::where('ci', $request->ci)->firstOrFail();

        // Traer el último check-in del día para ese empleado
        $lastCheckIn = CheckIn::where('employee_id', $employee->id)
            ->whereDate('created_at', today())
            ->latest()
            ->first();

        // Validar lógica de entrada/salida
        if ($request->type === 'entrada') {
            if ($lastCheckIn && $lastCheckIn->type === 'entrada') {
                return back()->withErrors(['type' => 'Ya registraste una entrada hoy. Debes marcar una salida antes.']);
            }
        }

        if ($request->type === 'salida') {
            if (!$lastCheckIn || $lastCheckIn->type === 'salida') {
                return back()->withErrors(['type' => 'No has registrado una entrada hoy.']);
            }
        }

        // Guardar la foto
        $photoPath = $request->file('photo')->store('checkins', 'public');

        // Crear la marcación
        CheckIn::create([
            'employee_id' => $employee->id,
            'type' => $request->type,
            'photo_path' => $photoPath,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
        ]);

        return redirect()->back()->with('success', 'Marcación registrada correctamente');
    }
}
