<?php

use App\Models\Deduction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── getTypeOptions / Labels / Colors ────────────────────────────────────────

it('getTypeOptions retorna los 3 tipos', function () {
    $options = Deduction::getTypeOptions();

    expect($options)->toHaveKeys(['legal', 'judicial', 'voluntary'])
        ->and($options)->toHaveCount(3);
});

it('getTypeLabels retorna etiquetas cortas para los 3 tipos', function () {
    $labels = Deduction::getTypeLabels();

    expect($labels['legal'])->toBe('Legal')
        ->and($labels['judicial'])->toBe('Judicial')
        ->and($labels['voluntary'])->toBe('Voluntaria');
});

it('getTypeColors retorna colores válidos de Filament para los 3 tipos', function () {
    $validColors = ['primary', 'success', 'warning', 'danger', 'info', 'gray'];
    $colors = Deduction::getTypeColors();

    expect($colors)->toHaveCount(3);
    foreach ($colors as $color) {
        expect($validColors)->toContain($color);
    }
});

// ─── fillable y persistencia ──────────────────────────────────────────────────

it('guarda y recupera el type correctamente', function () {
    foreach (['legal', 'judicial', 'voluntary'] as $i => $type) {
        $d = Deduction::create([
            'name'        => "Deducción {$type}",
            'code'        => "T{$i}",
            'type'        => $type,
            'calculation' => 'fixed',
            'amount'      => 10_000,
            'is_active'   => true,
        ]);

        expect($d->fresh()->type)->toBe($type);
    }
});

it('el valor por defecto de type es legal', function () {
    // Sin pasar type explícito — la migration pone 'legal' como default
    $d = Deduction::create([
        'name'        => 'Sin tipo explícito',
        'code'        => 'NT01',
        'calculation' => 'fixed',
        'amount'      => 5_000,
        'is_active'   => true,
    ]);

    expect($d->fresh()->type)->toBe('legal');
});
