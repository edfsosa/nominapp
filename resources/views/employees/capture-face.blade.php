@php
    $mode       = 'employee';
    $css        = 'resources/css/shared/capture-face.css';
    $js         = 'resources/js/shared/capture-face.js';
    $formAction = route('face.capture.store', $employee);
    $enrollment = null;

    $faceDate = null;
    if ($employee->has_face) {
        $lastApproved = $employee->faceEnrollments()
            ->where('status', 'approved')
            ->latest('reviewed_at')
            ->first();
        $faceDate = $lastApproved?->reviewed_at ?? $employee->updated_at;
    }
@endphp
@include('shared.capture-face')
