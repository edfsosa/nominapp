@props(['sectionId' => 'step2-title'])

<section class="card" aria-labelledby="{{ $sectionId }}" role="region">
    <h2 id="{{ $sectionId }}" tabindex="-1">Paso 2 · Datos de marcación</h2>

    <div class="row">
        <div class="form-group">
            <label for="eventType" class="label-spacing">
                Tipo de marcación
                <span class="sr-only">(requerido)</span>
            </label>
            <select id="eventType" disabled aria-disabled="true" aria-required="true">
                <option value="">— primero identifícate —</option>
            </select>
        </div>
    </div>

    <div class="row" role="group" aria-label="Controles de ubicación y marcación">
        <button id="btnGeo" type="button" class="btn btn-blue"
            aria-label="Obtener ubicación GPS actual"
            aria-describedby="geo-desc"
            disabled
            aria-disabled="true">
            Obtener ubicación
        </button>
        <button id="btnMark" type="button" class="btn btn-green"
            aria-label="Confirmar y registrar marcación"
            aria-describedby="geo-desc"
            disabled
            aria-disabled="true">
            Confirmar marcación
        </button>
    </div>

    <div class="row" role="group" aria-label="Coordenadas de ubicación">
        <div class="form-group">
            <label for="lat" class="label-spacing">
                Latitud
                <span class="sr-only">(solo lectura)</span>
            </label>
            <input id="lat" type="text" readonly inputmode="numeric" aria-readonly="true" aria-label="Latitud de la ubicación">
        </div>
        <div class="form-group">
            <label for="lng" class="label-spacing">
                Longitud
                <span class="sr-only">(solo lectura)</span>
            </label>
            <input id="lng" type="text" readonly inputmode="numeric" aria-readonly="true" aria-label="Longitud de la ubicación">
        </div>
    </div>
    <p id="geo-desc" class="status status-spacing" role="status" aria-live="polite">
        La ubicación es <strong>obligatoria</strong> para confirmar la marcación.
    </p>
</section>
