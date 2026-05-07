@extends('layouts.admin')

@section('content')
    <div class="container">

        <a href="{{ route('admin.applications.index') }}" class="btn btn-link p-0 mb-3">← Back</a>
        <h2 class="mb-3">Application Details</h2>
        <div class="mb-2">
            @php $map=['pending'=>'secondary','approved'=>'success','rejected'=>'danger']; @endphp
            <span class="badge bg-{{ $map[$application->status] ?? 'secondary' }}">{{ ucfirst($application->status) }}</span>
            @if ($application->reviewed_at)
                <small class="text-muted">Reviewed {{ $application->reviewed_at->diffForHumans() }} by
                    {{ $application->reviewer?->name }}</small>
            @endif
            @if ($application->review_remarks)
                <div><small><strong>Remarks:</strong> {{ $application->review_remarks }}</small></div>
            @endif
        </div>
        <!-- Reference Number Section -->
        <div class="card-body">
            <!-- Reference Number Section - Only show after approval -->
            @if ($application->status === \App\Models\Application\Application::STATUS_APPROVED)
                <div class="card mb-3">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-hashtag"></i> Reference Number</h5>
                        @if (empty($application->reference_number))
                            <span class="badge bg-warning">Not Assigned</span>
                        @else
                            <span class="badge bg-success">Assigned1</span>
                        @endif
                    </div>
                    <div class="card-body">
                        @if ($application->reference_number)
                            <!-- Display Mode: Show saved reference with edit button -->
                            <div id="referenceDisplay" class="d-flex justify-content-between align-items-center">
                                <div class="border border-primary p-1 flex-grow-1">
                                    <i class="fas fa-check-circle"></i>
                                    Reference Number: <strong>{{ $application->reference_number }}</strong>
                                </div>
                                <button type="button" class="btn btn-outline-primary ms-2" onclick="toggleReferenceEdit()">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                            </div>

                            <!-- Edit Mode: Hidden by default -->
                            <form id="referenceForm"
                                action="{{ route('admin.applications.update-reference', $application->id) }}" method="POST"
                                class="d-none d-flex gap-2">
                                @csrf
                                @method('PATCH')
                                <div class="flex-grow-1">
                                    <label for="reference_number" class="form-label">Reference Number (15
                                        digits)</label>
                                    <input type="text"
                                        class="form-control @error('reference_number') is-invalid @enderror"
                                        id="reference_number" name="reference_number"
                                        value="{{ $application->reference_number }}"
                                        placeholder="Enter 15-digit reference number" maxlength="15" pattern="[0-9]{0,15}">
                                    @error('reference_number')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="d-flex align-items-end gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Save
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="toggleReferenceEdit()">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                </div>
                            </form>
                        @else
                            <form id="referenceForm"
                                action="{{ route('admin.applications.update-reference', $application->id) }}"
                                method="POST" class="d-flex gap-2">
                                @csrf
                                @method('PATCH')
                                <div class="flex-grow-1">
                                    <label for="reference_number" class="form-label">Reference Number (15
                                        digits)</label>
                                    <input type="text"
                                        class="form-control @error('reference_number') is-invalid @enderror"
                                        id="reference_number" name="reference_number" value=""
                                        placeholder="Enter 15-digit reference number" maxlength="15" pattern="[0-9]{0,15}">
                                    @error('reference_number')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Assign Reference
                                    </button>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            @endif

        </div>
        @if ($application->was_corrected)
            <div class="border border-warning mb-3 p-3">
                <h5 class="alert-heading">
                    <i class="fas fa-redo"></i> Application Resubmitted
                </h5>
                <p><strong>Resubmitted on:</strong> {{ $application->resubmitted_at->format('M d, Y h:i A') }}</p>
                <p class="mb-2">
                    <i class="fas fa-info-circle"></i>
                    This application was previously sent back for corrections and has been resubmitted by the applicant.
                </p>

                {{-- Show what was requested to be corrected --}}
                @if ($application->correction_message)
                    <div class="mt-3 mb-3">
                        <p class="mb-1"><strong><i class="fas fa-clipboard-list"></i> Original Correction
                                Request:</strong></p>
                        <div class="bg-light p-3 rounded border" style="white-space: pre-line;">
                            {{ $application->correction_message }}</div>
                        <small class="text-muted">
                            <i class="fas fa-clock"></i> Requested on:
                            {{ $application->correction_requested_at?->format('M d, Y h:i A') }}
                        </small>
                    </div>
                @endif

                {{-- Show what fields were changed --}}
                @php
                    $latestChanges = $application
                        ->changes()
                        ->where('changed_at', $application->resubmitted_at)
                        ->orderBy('field_label')
                        ->get();
                @endphp

                @if ($latestChanges->isNotEmpty())
                    <div class="mt-3">
                        <p class="mb-2"><strong><i class="fas fa-edit"></i> Fields Changed by Applicant:</strong></p>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 25%">Field</th>
                                        <th style="width: 37.5%">Old Value</th>
                                        <th style="width: 37.5%">New Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($latestChanges as $change)
                                        <tr>
                                            <td><strong>{{ $change->field_label }}</strong></td>
                                            <td>
                                                @if ($change->old_value)
                                                    <span
                                                        class="text-danger text-decoration-line-through">{{ Str::limit($change->old_value, 50) }}</span>
                                                @else
                                                    <span class="text-muted fst-italic">Empty</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($change->new_value)
                                                    <span
                                                        class="text-success fw-bold">{{ Str::limit($change->new_value, 50) }}</span>
                                                @else
                                                    <span class="text-muted fst-italic">Empty</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <small class="text-muted mt-2 d-block">
                            <i class="fas fa-info-circle"></i> Total {{ $latestChanges->count() }} field(s) changed
                        </small>
                    </div>
                @else
                    <div class="mt-3">
                        <p class="text-muted mb-0">
                            <i class="fas fa-info-circle"></i> No field changes detected (applicant may have only
                            resubmitted without changes)
                        </p>
                    </div>
                @endif

                <div class="mt-3 pt-3 border-top">
                    <p class="mb-0">
                        <i class="fas fa-exclamation-triangle text-warning"></i>
                        <strong>Action Required:</strong> Please review the changes carefully to ensure all corrections have
                        been properly addressed.
                    </p>
                </div>
            </div>
        @endif

        @if ($application->application_type === 'Assessment Only')
            <div class="card mb-3">
                <div class="card-header bg-primary text-light">
                    <h5 class="mb-0"><i class="fas fa-money-bill-wave"></i> Payment Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Payment Status:</strong>
                                @php
                                    $paymentMap = [
                                        'pending' => ['badge' => 'warning', 'text' => 'Payment Required'],
                                        'submitted' => ['badge' => 'info', 'text' => 'Under Review'],
                                        'verified' => ['badge' => 'success', 'text' => 'Verified'],
                                        'rejected' => ['badge' => 'danger', 'text' => 'Rejected'],
                                    ];
                                    $paymentBadge = $paymentMap[$application->payment_status] ?? [
                                        'badge' => 'secondary',
                                        'text' => 'N/A',
                                    ];
                                @endphp
                                <span class="badge bg-{{ $paymentBadge['badge'] }}">{{ $paymentBadge['text'] }}</span>
                            </p>

                            @if ($application->payment_submitted_at)
                                <p><strong>Submitted At:</strong>
                                    {{ $application->payment_submitted_at->format('M d, Y h:i A') }}</p>
                            @endif

                            @if ($application->payment_remarks)
                                <p><strong>Remarks:</strong> <span
                                        class="text-danger">{{ $application->payment_remarks }}</span></p>
                            @endif
                        </div>

                        <div class="col-md-6">
                            @if ($application->payment_proof)
                                <p><strong>Payment Proof:</strong></p>
                                {{-- <a href="{{ Storage::url($application->payment_proof) }}" target="_blank"
                                    class="btn btn-sm btn-primary mb-2">
                                    <i class="bi bi-eye"></i> View Payment Proof
                                </a> --}}
                                <a href="{{ route('admin.applications.file', ['application' => $application->id, 'fileType' => 'payment-proof']) }}" 
                                    target="_blank" class="btn btn-sm btn-primary mb-2">
                                        <i class="bi bi-eye"></i> View Payment Proof
                                </a>

                                {{-- Admin actions for submitted payments --}}
                                @if ($application->payment_status === 'submitted')
                                    <div class="mt-2">
                                        <form action="{{ route('admin.payment.verify', $application->id) }}"
                                            method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success"
                                                onclick="return confirm('Verify this payment?')">
                                                <i class="fas fa-check"></i> Verify Payment
                                            </button>
                                        </form>

                                        <button class="btn btn-sm btn-danger" data-bs-toggle="modal"
                                            data-bs-target="#rejectPaymentModal">
                                            <i class="fas fa-times"></i> Reject Payment
                                        </button>
                                    </div>
                                @endif

                                {{-- Upload Official Receipt button (after payment is verified) --}}
                                @if ($application->payment_status === 'verified')
                                    <div class="mt-3">
                                        <p><strong>Official Receipt:</strong></p>
                                        @if ($application->official_receipt_photo)
                                            <a href="{{ route('admin.applications.file', ['application' => $application->id, 'fileType' => 'official-receipt']) }}"
                                                target="_blank" class="btn btn-sm btn-info mb-2">
                                                <i class="bi bi-file-earmark-text"></i> View Official Receipt
                                            </a>
                                            <small class="text-muted d-block">
                                                Uploaded:
                                                {{ $application->official_receipt_uploaded_at->format('M d, Y h:i A') }}
                                            </small>
                                        @endif

                                        <button class="btn btn-sm btn-success mt-2" data-bs-toggle="modal"
                                            data-bs-target="#uploadOfficialReceiptModal">
                                            <i class="fas fa-upload"></i>
                                            {{ $application->official_receipt_photo ? 'Replace' : 'Upload' }} Official
                                            Receipt
                                        </button>
                                    </div>
                                @endif
                            @else
                                <p class="text-muted"><i class="fas fa-info-circle"></i> No payment proof uploaded yet.
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if ($application->correction_requested)
            <div class="card border-danger mb-4">
                <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">

                    <span>
                        <i class="fas fa-exclamation-triangle"></i> Correction Requested
                    </span>

                    <small>
                        Requested on: {{ $application->correction_requested_at->format('M d, Y h:i A') }}
                    </small>

                </div>

                <div class="card-body">
                    <p><strong>Message sent to applicant:</strong></p>

                    <div class="bg-light p-3 rounded border mb-3">
                        {{ $application->correction_message }}
                    </div>

                    <hr>

                    <p class="mb-0 small text-muted">
                        <i class="fas fa-info-circle"></i>
                        Waiting for the applicant to make corrections and resubmit.
                        The application status will return to <strong>"Pending"</strong> after resubmission.
                    </p>

                </div>
            </div>
        @endif
        <div class="card mt-3 border border-primary">
            <div class="card-header bg-primary text-light">
                Application Summary
            </div>

            <div class="card-body">
                <div class="row">

                    <!-- LEFT COLUMN -->
                    <div class="col-md-6">

                        <p><strong class="text-primary">Title of Assessment</strong><br>
                            {{ $application->title_ofAssessment_applied_for ?? $application->title_of_assessment_applied_for }}
                        </p>

                        <p><strong class="text-primary">Full Name</strong><br>
                            {{ $application->surname }},
                            {{ $application->firstname }}
                            @if ($application->middlename)
                                {{ $application->middlename }}
                            @endif
                            @if ($application->name_extension)
                                {{ $application->name_extension }}
                            @endif
                        </p>

                        <p><strong class="text-primary">Address</strong><br>
                            {{ $application->street_address }}
                            @if ($application->barangay_name)
                                , {{ $application->barangay_name }}
                            @endif
                            @if ($application->city_name)
                                , {{ $application->city_name }}
                            @endif
                            @if ($application->province_name)
                                , {{ $application->province_name }}
                            @endif
                            @if ($application->region_name)
                                , {{ $application->region_name }}
                            @endif
                            @if ($application->zip_code)
                                , {{ $application->zip_code }}
                            @endif
                        </p>

                        <p><strong class="text-primary">Contact</strong><br>
                            @if ($application->mobile)
                                Mobile: {{ $application->mobile }}
                            @endif
                            @if ($application->email)
                                <br>Email: {{ $application->email }}
                            @endif
                        </p>

                        <p><strong class="text-primary">Personal</strong><br>
                            @if ($application->sex)
                                Sex: {{ ucfirst($application->sex) }}
                            @endif
                            @if ($application->civil_status)
                                <br>Civil Status: {{ $application->civil_status }}
                            @endif
                        </p>

                    </div>

                    <!-- RIGHT COLUMN -->
                    <div class="col-md-6">

                        <p><strong class="text-primary">Birth Information</strong><br>
                            @if ($application->birthdate)
                                Birthdate:
                                {{ \Illuminate\Support\Carbon::parse($application->birthdate)->toFormattedDateString() }}
                            @endif
                            @if ($application->birthplace)
                                <br>Birthplace: {{ $application->birthplace }}
                            @endif
                            @if (!is_null($application->age))
                                <br>Age: {{ $application->age }}
                            @endif
                        </p>

                        <p><strong class="text-primary">Education & Employment</strong><br>
                            @if ($application->highest_educational_attainment)
                                HEA: {{ $application->highest_educational_attainment }}
                            @endif
                            @if ($application->employment_status)
                                <br>Employment Status: {{ $application->employment_status }}
                            @endif
                        </p>

                        <p><strong class="text-primary">Parents</strong><br>
                            @if ($application->mothers_name)
                                Mother: {{ $application->mothers_name }}
                            @endif
                            @if ($application->fathers_name)
                                <br>Father: {{ $application->fathers_name }}
                            @endif
                        </p>

                        <p><strong class="text-primary">Submitted</strong><br>
                            {{ $application->created_at?->toDayDateTimeString() }}
                        </p>

                    </div>

                </div>
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header">Work Experience</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Company</th>
                                <th>Position</th>
                                <th>Inclusive Dates</th>
                                <th>Monthly Salary</th>
                                <th>Status</th>
                                <th>Years</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($application->workExperiences as $exp)
                                <tr>
                                    <td>{{ $exp->company_name }}</td>
                                    <td>{{ $exp->position }}</td>
                                    <td>
                                        {{ $exp->date_from }} - {{ $exp->date_to }}
                                    </td>
                                    <td>{{ $exp->monthly_salary }}</td>
                                    <td>{{ $exp->appointment_status }}</td>
                                    <td>{{ $exp->years_experience }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">Other Training Seminars Attended</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Venue</th>
                                <th>Inclusive Dates</th>
                                <th>No. of Hours</th>
                                <th>Conducted By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($application->trainings as $t)
                                <tr>
                                    <td>{{ $t->title }}</td>
                                    <td>{{ $t->venue }}</td>
                                    <td>{{ $t->date_from }} - {{ $t->date_to }}</td>
                                    <td>{{ $t->hours }}</td>
                                    <td>{{ $t->conducted_by }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">Licensure Examination(s) Passed</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Year</th>
                                <th>Venue</th>
                                <th>Rating</th>
                                <th>Remarks</th>
                                <th>Expiry Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($application->licensureExams as $e)
                                <tr>
                                    <td>{{ $e->title }}</td>
                                    <td>{{ $e->year_taken }}</td>
                                    <td>{{ $e->exam_venue }}</td>
                                    <td>{{ $e->rating }}</td>
                                    <td>{{ $e->remarks }}</td>
                                    <td>{{ $e->expiry_date }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">Competency Assessment(s) Passed</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Qualification Level</th>
                                <th>Industry Sector</th>
                                <th>Certificate No.</th>
                                <th>Date of Issuance</th>
                                <th>Expiration Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($application->competencyAssessments as $c)
                                <tr>
                                    <td>{{ $c->title }}</td>
                                    <td>{{ $c->qualification_level }}</td>
                                    <td>{{ $c->industry_sector }}</td>
                                    <td>{{ $c->certificate_number }}</td>
                                    <td>{{ $c->date_of_issuance }}</td>
                                    <td>{{ $c->expiration_date }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @if ($application->application_type === 'TWSP' && $application->twspDocument)
            <div class="card mt-3 mb-3 border border-primary">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">TWSP Required Documents</h6>
                    <div>
                        <a href="{{ route('admin.applications.twsp_documents_pdf', $application) }}" target="_blank"
                            class="btn btn-light me-1">
                            <i class="bi bi-file-pdf mx-1"></i>Print Documents
                        </a>

                    </div>
                </div>
                <div class="card-body p-3 text-primary">
                    <div class="row g-2">
                        {{-- PSA Birth Certificate --}}
                        <div class="col-md-4 col-sm-6">
                            <div class="border border-primary rounded p-2 h-100">
                                <div class="fw-bold small mb-2">PSA Birth Certificate</div>
                                @if ($application->twspDocument->psa_birth_certificate)
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="{{ Storage::url($application->twspDocument->psa_birth_certificate) }}"
                                            class="img-thumbnail" style="width: 50px; height: 50px; object-fit: cover;"
                                            onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22%3E%3Crect fill=%22%23e9ecef%22 width=%22100%22 height=%22100%22/%3E%3Ctext fill=%22%236c757d%22 x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 font-size=%2214%22%3EPDF%3C/text%3E%3C/svg%3E';">
                                        <div>
                                            <a href="{{ Storage::url($application->twspDocument->psa_birth_certificate) }}"
                                                target="_blank" class="btn btn-sm btn-primary me-1">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="{{ Storage::url($application->twspDocument->psa_birth_certificate) }}"
                                                download class="btn btn-sm btn-success">
                                                <i class="bi bi-download"></i>
                                            </a>
                                        </div>
                                    </div>
                                @else
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="border rounded bg-light d-flex align-items-center justify-content-center"
                                            style="width: 50px; height: 50px;">
                                            <i class="fas fa-file text-muted"></i>
                                        </div>
                                        <span class="text-muted small">Not uploaded</span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- PSA Marriage Contract --}}
                        <div class="col-md-4 col-sm-6">
                            <div class="border border-primary rounded p-2 h-100">
                                <div class="fw-bold small mb-2">PSA Marriage Contract</div>
                                @if ($application->twspDocument->psa_marriage_contract)
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="{{ Storage::url($application->twspDocument->psa_marriage_contract) }}"
                                            class="img-thumbnail" style="width: 50px; height: 50px; object-fit: cover;"
                                            onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22%3E%3Crect fill=%22%23e9ecef%22 width=%22100%22 height=%22100%22/%3E%3Ctext fill=%22%236c757d%22 x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 font-size=%2214%22%3EPDF%3C/text%3E%3C/svg%3E';">
                                        <div>
                                            <a href="{{ Storage::url($application->twspDocument->psa_marriage_contract) }}"
                                                target="_blank" class="btn btn-sm btn-primary me-1">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="{{ Storage::url($application->twspDocument->psa_marriage_contract) }}"
                                                download class="btn btn-sm btn-success">
                                                <i class="bi bi-download"></i>
                                            </a>
                                        </div>
                                    </div>
                                @else
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="border rounded bg-light d-flex align-items-center justify-content-center"
                                            style="width: 50px; height: 50px;">
                                            <i class="fas fa-file text-muted"></i>
                                        </div>
                                        <span class="text-muted small">N/A</span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- High School Document --}}
                        <div class="col-md-4 col-sm-6">
                            <div class="border border-primary rounded p-2 h-100">
                                <div class="fw-bold small mb-2">HS Card/TOR/Diploma</div>
                                @if ($application->twspDocument->high_school_document)
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="{{ Storage::url($application->twspDocument->high_school_document) }}"
                                            class="img-thumbnail" style="width: 50px; height: 50px; object-fit: cover;"
                                            onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22%3E%3Crect fill=%22%23e9ecef%22 width=%22100%22 height=%22100%22/%3E%3Ctext fill=%22%236c757d%22 x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 font-size=%2214%22%3EPDF%3C/text%3E%3C/svg%3E';">
                                        <div>
                                            <a href="{{ Storage::url($application->twspDocument->high_school_document) }}"
                                                target="_blank" class="btn btn-sm btn-primary me-1">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="{{ Storage::url($application->twspDocument->high_school_document) }}"
                                                download class="btn btn-sm btn-success">
                                                <i class="bi bi-download"></i>
                                            </a>
                                        </div>
                                    </div>
                                @else
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="border rounded bg-light d-flex align-items-center justify-content-center"
                                            style="width: 50px; height: 50px;">
                                            <i class="fas fa-file text-muted"></i>
                                        </div>
                                        <span class="text-muted small">Not uploaded</span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Certificate of Indigency --}}
                        <div class="col-md-4 col-sm-6">
                            <div class="border border-primary rounded p-2 h-100">
                                <div class="fw-bold small mb-2">Certificate of Indigency</div>
                                @if ($application->twspDocument->certificate_of_indigency)
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="{{ Storage::url($application->twspDocument->certificate_of_indigency) }}"
                                            class="img-thumbnail" style="width: 50px; height: 50px; object-fit: cover;"
                                            onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22%3E%3Crect fill=%22%23e9ecef%22 width=%22100%22 height=%22100%22/%3E%3Ctext fill=%22%236c757d%22 x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 font-size=%2214%22%3EPDF%3C/text%3E%3C/svg%3E';">
                                        <div>
                                            <a href="{{ Storage::url($application->twspDocument->certificate_of_indigency) }}"
                                                target="_blank" class="btn btn-sm btn-primary me-1">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="{{ Storage::url($application->twspDocument->certificate_of_indigency) }}"
                                                download class="btn btn-sm btn-success">
                                                <i class="bi bi-download"></i>
                                            </a>
                                        </div>
                                    </div>
                                @else
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="border rounded bg-light d-flex align-items-center justify-content-center"
                                            style="width: 50px; height: 50px;">
                                            <i class="fas fa-file text-muted"></i>
                                        </div>
                                        <span class="text-muted small">Not uploaded</span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- 1x1 ID Pictures --}}
                        <div class="col-md-4 col-sm-6">
                            <div class="border border-primary rounded p-2 h-100">
                                <div class="fw-bold small mb-2">1x1 ID Pictures (4 pcs)</div>
                                @if ($application->twspDocument->id_pictures_1x1)
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="d-flex gap-1">
                                            @foreach ($application->twspDocument->id_pictures_1x1 as $index => $path)
                                                <a href="{{ Storage::url($path) }}" target="_blank"
                                                    title="1x1 ID {{ $index + 1 }}">
                                                    <img src="{{ Storage::url($path) }}" alt="1x1 {{ $index + 1 }}"
                                                        class="img-thumbnail"
                                                        style="width: 35px; height: 35px; object-fit: cover;">
                                                </a>
                                            @endforeach
                                        </div>
                                        <div>
                                            <a href="{{ Storage::url($application->twspDocument->id_pictures_1x1[0]) }}"
                                                target="_blank" class="btn btn-sm btn-primary me-1">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="{{ Storage::url($application->twspDocument->id_pictures_1x1[0]) }}"
                                                download class="btn btn-sm btn-success">
                                                <i class="bi bi-download"></i>
                                            </a>
                                        </div>
                                    </div>
                                @else
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="border rounded bg-light d-flex align-items-center justify-content-center"
                                            style="width: 50px; height: 50px;">
                                            <i class="fas fa-images text-muted"></i>
                                        </div>
                                        <span class="text-muted small">Not uploaded</span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Passport Size Pictures --}}
                        <div class="col-md-4 col-sm-6">
                            <div class="border border-primary rounded p-2 h-100">
                                <div class="fw-bold small mb-2">Passport Size (4 pcs)</div>
                                @if ($application->twspDocument->id_pictures_passport)
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="d-flex gap-1">
                                            @foreach ($application->twspDocument->id_pictures_passport as $index => $path)
                                                <a href="{{ Storage::url($path) }}" target="_blank"
                                                    title="Passport {{ $index + 1 }}">
                                                    <img src="{{ Storage::url($path) }}"
                                                        alt="Passport {{ $index + 1 }}" class="img-thumbnail"
                                                        style="width: 35px; height: 45px; object-fit: cover;">
                                                </a>
                                            @endforeach
                                        </div>
                                        <div>
                                            <a href="{{ Storage::url($application->twspDocument->id_pictures_passport[0]) }}"
                                                target="_blank" class="btn btn-sm btn-primary me-1">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="{{ Storage::url($application->twspDocument->id_pictures_passport[0]) }}"
                                                download class="btn btn-sm btn-success">
                                                <i class="bi bi-download"></i>
                                            </a>
                                        </div>
                                    </div>
                                @else
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="border rounded bg-light d-flex align-items-center justify-content-center"
                                            style="width: 50px; height: 50px;">
                                            <i class="fas fa-images text-muted"></i>
                                        </div>
                                        <span class="text-muted small">Not uploaded</span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Government/School ID --}}
                        <div class="col-md-4 col-sm-6">
                            <div class="border border-primary rounded p-2 h-100">
                                <div class="fw-bold small mb-2">Gov/School ID (2 pcs)</div>
                                @if ($application->twspDocument->government_school_id)
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="d-flex gap-1">
                                            @foreach ($application->twspDocument->government_school_id as $index => $path)
                                                <a href="{{ Storage::url($path) }}" target="_blank"
                                                    title="ID {{ $index + 1 }}">
                                                    <img src="{{ Storage::url($path) }}" alt="ID {{ $index + 1 }}"
                                                        class="img-thumbnail"
                                                        style="width: 50px; height: 35px; object-fit: cover;"
                                                        onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22%3E%3Crect fill=%22%23e9ecef%22 width=%22100%22 height=%22100%22/%3E%3Ctext fill=%22%236c757d%22 x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 font-size=%2212%22%3EID%3C/text%3E%3C/svg%3E';">
                                                </a>
                                            @endforeach
                                        </div>
                                        <div>
                                            <a href="{{ Storage::url($application->twspDocument->government_school_id[0]) }}"
                                                target="_blank" class="btn btn-sm btn-primary me-1">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="{{ Storage::url($application->twspDocument->government_school_id[0]) }}"
                                                download class="btn btn-sm btn-success">
                                                <i class="bi bi-download"></i>
                                            </a>
                                        </div>
                                    </div>
                                @else
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="border rounded bg-light d-flex align-items-center justify-content-center"
                                            style="width: 50px; height: 50px;">
                                            <i class="fas fa-id-card text-muted"></i>
                                        </div>
                                        <span class="text-muted small">Not uploaded</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif


        <form method="POST" action="{{ route('admin.applications.approve', $application) }}" class="d-inline">
            @csrf
            @if ($application->status === \App\Models\Application\Application::STATUS_PENDING)
                <form method="POST" action="{{ route('admin.applications.approve', $application) }}" class="d-inline">
                    @csrf
                    <button class="btn btn-success" onclick="return confirm('Approve this application?')">
                        <i class="fas fa-check"></i> Approve Application
                    </button>
                </form>

                <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#requestCorrectionModal">
                    <i class="fas fa-edit"></i> Request Correction
                </button>
                <form method="POST" action="{{ route('admin.applications.reject', $application) }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="remarks" value="">
                    <button class="btn btn-danger" onclick="return confirm('Reject this application?')">Reject</button>
                </form>
            @endif
            <a href="{{ route('admin.applications.print_pdf', $application) }}" target="_blank"
                class="btn btn-primary">Print TESDA Form(PDF)</a>

    </div>

    {{-- Request Correction Modal --}}
    @include('admin.applicant.component.request-correction', ['application' => $application])
    {{-- Payment Rejection Modal --}}
    @include('admin.applicant.component.reject-payment', ['application' => $application])
    {{-- Upload Official Receipt Modal --}}
    @include('admin.applicant.component.upload-official-receipt', ['application' => $application])


@endsection
<script>
    function toggleReferenceEdit() {
        const display = document.getElementById('referenceDisplay');
        const form = document.getElementById('referenceForm');

        if (display) {
            display.classList.toggle('d-none');
        }
        if (form) {
            form.classList.toggle('d-none');
        }
    }
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('referenceForm');
        if (form) {
            form.addEventListener('submit', function() {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                }
            });
        }
    });
</script>
<style>
    @media print {

        .btn,
        nav,
        .sidebar,
        .card-header button {
            display: none !important;
        }

        .card {
            border: 1px solid #000;
            page-break-inside: avoid;
        }

        img {
            max-width: 150px;
        }
    }
</style>
