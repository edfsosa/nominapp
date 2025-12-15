@props([
    'id',
    'type' => 'success',
    'title' => '',
    'description' => '',
    'buttonText' => 'Aceptar'
])

@php
    $typeClass = $type === 'error' ? 'modal-error' : 'modal-success';
    $titleId = $id . 'Title';
    $descId = $id . 'Desc';
    $closeButtonId = $type === 'error' ? 'closeErrorModal' : 'closeModal';
@endphp

<div id="{{ $id }}" class="modal hidden" role="dialog" aria-modal="true"
    aria-labelledby="{{ $titleId }}" aria-describedby="{{ $descId }}">
    <div class="modal-content {{ $typeClass }}">
        <h2 id="{{ $titleId }}">{{ $title }}</h2>
        <p id="{{ $descId }}">{{ $description }}</p>
        <button id="{{ $closeButtonId }}" type="button">{{ $buttonText }}</button>
    </div>
</div>
