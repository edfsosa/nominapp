@php
    $mode       = 'enrollment';
    $css        = 'resources/css/shared/capture-face.css';
    $js         = 'resources/js/shared/capture-face.js';
    $formAction = route('face-enrollment.store', $enrollment->token);
@endphp
@include('shared.capture-face')
