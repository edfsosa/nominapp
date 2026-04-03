<?php

use App\Models\Perception;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── getAffectsIpsForType ────────────────────────────────────────────────────

it('salary fuerza affects_ips = true', function () {
    expect(Perception::getAffectsIpsForType('salary'))->toBeTrue();
});

it('viaticos fuerza affects_ips = false', function () {
    expect(Perception::getAffectsIpsForType('viaticos'))->toBeFalse();
});

it('subsidy fuerza affects_ips = false', function () {
    expect(Perception::getAffectsIpsForType('subsidy'))->toBeFalse();
});

it('other retorna null (valor libre)', function () {
    expect(Perception::getAffectsIpsForType('other'))->toBeNull();
});

it('tipo desconocido retorna null', function () {
    expect(Perception::getAffectsIpsForType('anything_else'))->toBeNull();
});

// ─── Hook saving — auto-setting affects_ips ──────────────────────────────────

it('al crear con type=salary fuerza affects_ips=true aunque se pase false', function () {
    $perception = Perception::create([
        'name'        => 'Bono Salarial',
        'code'        => 'BS001',
        'type'        => 'salary',
        'calculation' => 'fixed',
        'amount'      => 100_000,
        'affects_ips' => false, // intento explícito de override
        'is_active'   => true,
    ]);

    expect($perception->affects_ips)->toBeTrue();
});

it('al crear con type=viaticos fuerza affects_ips=false aunque se pase true', function () {
    $perception = Perception::create([
        'name'        => 'Viáticos de Traslado',
        'code'        => 'VT001',
        'type'        => 'viaticos',
        'calculation' => 'fixed',
        'amount'      => 50_000,
        'affects_ips' => true, // intento explícito de override
        'is_active'   => true,
    ]);

    expect($perception->affects_ips)->toBeFalse();
});

it('al crear con type=subsidy fuerza affects_ips=false', function () {
    $perception = Perception::create([
        'name'        => 'Subsidio de Almuerzo',
        'code'        => 'SA001',
        'type'        => 'subsidy',
        'calculation' => 'fixed',
        'amount'      => 30_000,
        'affects_ips' => true,
        'is_active'   => true,
    ]);

    expect($perception->affects_ips)->toBeFalse();
});

it('al crear con type=other respeta el valor pasado para affects_ips', function () {
    $withIps = Perception::create([
        'name'        => 'Otro con IPS',
        'code'        => 'OT001',
        'type'        => 'other',
        'calculation' => 'fixed',
        'amount'      => 20_000,
        'affects_ips' => true,
        'is_active'   => true,
    ]);

    $withoutIps = Perception::create([
        'name'        => 'Otro sin IPS',
        'code'        => 'OT002',
        'type'        => 'other',
        'calculation' => 'fixed',
        'amount'      => 20_000,
        'affects_ips' => false,
        'is_active'   => true,
    ]);

    expect($withIps->affects_ips)->toBeTrue()
        ->and($withoutIps->affects_ips)->toBeFalse();
});

it('al actualizar type de other a salary actualiza affects_ips automáticamente', function () {
    $perception = Perception::create([
        'name'        => 'Bono Flexible',
        'code'        => 'BF001',
        'type'        => 'other',
        'calculation' => 'fixed',
        'amount'      => 80_000,
        'affects_ips' => false,
        'is_active'   => true,
    ]);

    expect($perception->affects_ips)->toBeFalse(); // other respeta el valor

    $perception->update(['type' => 'salary']);
    $perception->refresh();

    expect($perception->affects_ips)->toBeTrue(); // salary fuerza true
});

it('al actualizar type de salary a viaticos cambia affects_ips a false', function () {
    $perception = Perception::create([
        'name'        => 'Viatico Ex Salarial',
        'code'        => 'VS001',
        'type'        => 'salary',
        'calculation' => 'fixed',
        'amount'      => 60_000,
        'affects_ips' => true,
        'is_active'   => true,
    ]);

    $perception->update(['type' => 'viaticos']);
    $perception->refresh();

    expect($perception->affects_ips)->toBeFalse();
});

it('no falla al crear percepción sin type (type=null no dispara el hook)', function () {
    // Este caso ocurre en seeders o tests antiguos que no proveen type
    expect(fn() => Perception::create([
        'name'        => 'Sin tipo',
        'code'        => 'NT001',
        'calculation' => 'fixed',
        'amount'      => 10_000,
        'affects_ips' => true,
        'is_active'   => true,
    ]))->not->toThrow(\Throwable::class);
});

// ─── Métodos estáticos de labels/colores/opciones ────────────────────────────

it('getTypeOptions retorna los 4 tipos', function () {
    $options = Perception::getTypeOptions();

    expect($options)->toHaveKeys(['salary', 'viaticos', 'subsidy', 'other'])
        ->and($options)->toHaveCount(4);
});

it('getTypeLabels retorna etiquetas cortas para los 4 tipos', function () {
    $labels = Perception::getTypeLabels();

    expect($labels)->toHaveKeys(['salary', 'viaticos', 'subsidy', 'other'])
        ->and($labels['salary'])->toBe('Salarial')
        ->and($labels['viaticos'])->toBe('Viáticos')
        ->and($labels['subsidy'])->toBe('Subsidio')
        ->and($labels['other'])->toBe('Otro');
});

it('getTypeColors retorna colores válidos de Filament para los 4 tipos', function () {
    $validColors = ['primary', 'success', 'warning', 'danger', 'info', 'gray'];
    $colors = Perception::getTypeColors();

    expect($colors)->toHaveCount(4);
    foreach ($colors as $color) {
        expect($validColors)->toContain($color);
    }
});
