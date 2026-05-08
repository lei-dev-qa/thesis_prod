<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\StoreApplicationRequest;
use App\Models\Application\Application;
use App\Models\Application\ApplicationView; 
use Illuminate\Support\Facades\DB;
use App\Models\TWSP\TwspDocument;
use Illuminate\Support\Facades\Storage;
use App\Notifications\Application\ApplicationSubmittedNotification;
use App\Notifications\Application\ApplicationResubmittedNotification;


class ApplicantController extends Controller
{

    public function dashboard()
    {
        $twspApps = Application::where('user_id', auth()->id())
            ->where('application_type', 'TWSP')
            ->with(['assessmentResult.cocResults', 'trainingBatch', 'assessmentBatch'])
            ->latest()
            ->get();

        $assessmentApps = Application::where('user_id', auth()->id())
            ->where('application_type', 'Assessment Only')
            ->with(['assessmentResult.cocResults', 'assessmentBatch'])
            ->latest()
            ->get();

        $applications = $twspApps->merge($assessmentApps)->sortByDesc('created_at');

        // Count eligible applicants PER NC PROGRAM
        $eligibleCountsByProgram = [];

        foreach ($applications as $app) {
            $programName = $app->title_of_assessment_applied_for;
            
            // Count eligible for assessment (includes new + reassessment applicants)
            if (!isset($eligibleCountsByProgram[$programName]['assessment'])) {
                $eligibleCountsByProgram[$programName]['assessment'] = Application::where('title_of_assessment_applied_for', $programName)
                    ->where(function($query) {
                        // NEW Assessment Only applicants
                        $query->where('application_type', 'Assessment Only')
                            ->where('status', Application::STATUS_APPROVED)
                            ->where('payment_status', Application::PAYMENT_STATUS_VERIFIED)
                            ->whereNull('assessment_batch_id');
                    })
                    ->orWhere(function($query) use ($programName) {
                        // NEW TWSP applicants
                        $query->where('title_of_assessment_applied_for', $programName)
                            ->where('application_type', 'TWSP')
                            ->where('status', Application::STATUS_APPROVED)
                            ->where('training_status', Application::TRAINING_STATUS_COMPLETED)
                            ->whereNull('assessment_batch_id');
                    })
                    ->orWhere(function($query) use ($programName) {
                        // REASSESSMENT applicants (failed + payment verified + no batch)
                        $query->where('title_of_assessment_applied_for', $programName)
                            ->whereHas('assessmentResult', function($q) {
                                $q->where('result', 'Not Yet Competent');
                            })
                            ->where('reassessment_payment_status', 'verified')
                            ->whereNull('assessment_batch_id');
                    })
                    ->count();
            }
            
            // Count eligible for training (TWSP only)
            if (!isset($eligibleCountsByProgram[$programName]['training'])) {
                $eligibleCountsByProgram[$programName]['training'] = Application::where('title_of_assessment_applied_for', $programName)
                    ->where('application_type', 'TWSP')
                    ->where('status', Application::STATUS_APPROVED)
                    ->whereNull('training_batch_id')
                    ->count();
            }
        }

        return view('applicant.dashboard', compact(
            'twspApps', 
            'assessmentApps', 
            'applications', 
            'eligibleCountsByProgram'
        ));
    }

    // Show the application form
    public function create()
    {
        return view('applicant.apply');
    }
    // Store the application
    public function store(Application $application, StoreApplicationRequest $request)
    {
        DB::transaction(function () use ($request) {
                // Prepare application data
                $data = array_merge(
                    $request->safe()->except(['work_experiences','trainings','licensure_exams','competency_assessments','psa_birth_certificate',
                                                'psa_marriage_contract',
                                                'high_school_document',
                                                'id_pictures_1x1',
                                                'id_pictures_passport',
                                                'government_school_id',
                                                'certificate_of_indigency',
                                                'learner_classification_other']),
                    [
                        'user_id' => $request->user()->id,
                        'status' => Application::STATUS_PENDING,
                        'application_type' => $request->input('application_type'),
                    ]
                );
                $data['surname'] = strtoupper($data['surname'] ?? '');
                $data['firstname'] = strtoupper($data['firstname'] ?? '');
                $data['middlename'] = strtoupper($data['middlename'] ?? '');
                $data['middleinitial'] = strtoupper($data['middleinitial'] ?? '');
                // Handle learner classification "others" text
                $learnerClassification = $request->input('learner_classification', []);
                if ($request->filled('learner_classification_other')) {
                    $learnerClassification[] = $request->input('learner_classification_other');
                }
                $data['learner_classification'] = $learnerClassification;
                // Handle photo upload
                if ($request->hasFile('photo')) {
                    $data['photo'] = $request->file('photo')->store('applicant-photos');
                }

                $application = Application::create($data);

                if ($request->input('application_type') === 'TWSP') {
                    $twspData = [];
                    
                    // Single file uploads
                    if ($request->hasFile('psa_birth_certificate')) {
                        $twspData['psa_birth_certificate'] = $request->file('psa_birth_certificate')
                            ->store('twsp_documents/birth_certificates');
                    }
                    
                    if ($request->hasFile('psa_marriage_contract')) {
                        $twspData['psa_marriage_contract'] = $request->file('psa_marriage_contract')
                            ->store('twsp_documents/marriage_contracts');
                    }
                    
                    if ($request->hasFile('high_school_document')) {
                        $twspData['high_school_document'] = $request->file('high_school_document')
                            ->store('twsp_documents/high_school');
                    }
                    
                    if ($request->hasFile('certificate_of_indigency')) {
                        $twspData['certificate_of_indigency'] = $request->file('certificate_of_indigency')
                            ->store('twsp_documents/indigency');
                    }
                    
                    // Multiple file uploads
                    if ($request->hasFile('id_pictures_1x1')) {
                        $paths = [];
                        foreach ($request->file('id_pictures_1x1') as $file) {
                            $paths[] = $file->store('twsp_documents/id_1x1');
                        }
                        $twspData['id_pictures_1x1'] = $paths;
                    }
                    
                    if ($request->hasFile('id_pictures_passport')) {
                        $paths = [];
                        foreach ($request->file('id_pictures_passport') as $file) {
                            $paths[] = $file->store('twsp_documents/id_passport');
                        }
                        $twspData['id_pictures_passport'] = $paths;
                    }
                    
                    if ($request->hasFile('government_school_id')) {
                        $paths = [];
                        foreach ($request->file('government_school_id') as $file) {
                            $paths[] = $file->store('twsp_documents/gov_school_id');
                        }
                        $twspData['government_school_id'] = $paths;
                }
                
                // Create TWSP document record
                $application->twspDocument()->create($twspData);
            }

                foreach ($request->input('work_experiences', []) as $exp) {
                    $application->workExperiences()->create($exp);
                }
                foreach ($request->input('trainings', []) as $t) {
                    $application->trainings()->create($t);
                }
                foreach ($request->input('licensure_exams', []) as $e) {
                    $application->licensureExams()->create($e);
                }
                foreach ($request->input('competency_assessments', []) as $c) { 
                    $application->competencyAssessments()->create($c);
                }
                // Send notification after successful transaction
                $application->user->notify(new ApplicationSubmittedNotification($application)); 
            });     

            return redirect()->route('applicant.dashboard')->with('success', 'Application submitted.');
    }

    public function show(Application $application)
    {
        abort_unless($application->user_id === auth()->id(), 403);
        ApplicationView::firstOrCreate(
            [
                'application_id' => $application->id,
                'user_id' => auth()->id(),
            ],
            [
                'viewed_at' => now(),
                'view_type' => 'detail',
            ]
        );
        $application->load([
            'user',
            'workExperiences',
            'trainings',
            'licensureExams',
            'competencyAssessments',
            'twspDocument',
            'changes' => function($query) use ($application) {
                $query->where('changed_at', $application->resubmitted_at)
                    ->orderBy('field_label');
            }
        ]);

        $application->load(['workExperiences','trainings','licensureExams','competencyAssessments']);
        return view('applicant.application-show', compact('application'));
    }
    public function edit(Application $application)
    {
        // Verify ownership
        abort_unless($application->user_id === auth()->id(), 403);
        
        // Only allow editing if correction is requested
        if (!$application->correction_requested) {
            return redirect()->route('applicant.dashboard')
                ->with('error', 'This application cannot be edited.');
        }
        
        // Load relationships
        $application->load([
            'workExperiences',
            'trainings',
            'licensureExams',
            'competencyAssessments',
            'twspDocument'
        ]);
        
        return view('applicant.edit', compact('application'));
    }
    public function update(Request $request, Application $application)
    {
        // Only allow updating if correction is requested
        if (!$application->correction_requested) {
            return redirect()->route('applicant.dashboard')
                ->with('error', 'This application cannot be updated.');
        }

        // Validate the request
        $validated = $request->validate([
            'title_of_assessment_applied_for' => 'required|string|max:255',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'surname' => 'required|string|max:255',
            'firstname' => 'required|string|max:255',
            'middlename' => 'nullable|string|max:255',
            'middleinitial' => 'nullable|string|max:10',
            'name_extension' => 'nullable|string|max:10',
            'region_code' => 'required|string',
            'region_name' => 'required|string',
            'province_code' => 'required|string',
            'province_name' => 'required|string',
            'city_code' => 'required|string',
            'city_name' => 'required|string',
            'barangay_code' => 'required|string',
            'barangay_name' => 'required|string',
            'district' => 'nullable|string|max:255',
            'street_address' => 'required|string|max:500',
            'zip_code' => 'required|string|max:10',
            'mothers_name' => 'required|string|max:255',
            'fathers_name' => 'required|string|max:255',
            'sex' => 'required|in:male,female',
            'civil_status' => 'required|in:SINGLE,MARRIED,WIDOW/ER,SEPARATED',
            'mobile' => 'required|string|max:20',
            'email' => 'required|email|max:255',
            'highest_educational_attainment' => 'required|string',
            'employment_status' => 'required|string',
            'birthdate' => 'required|date',
            'birthplace' => 'required|string|max:255',
            'age' => 'required|integer|min:1|max:120',

            'birthplace_region_code' => 'nullable|string',
            'birthplace_region' => 'nullable|string',
            'birthplace_province_code' => 'nullable|string',
            'birthplace_province' => 'nullable|string',
            'birthplace_city_code' => 'nullable|string',
            'birthplace_city' => 'nullable|string',
            
            // Parent/Guardian fields
            'parent_guardian_name' => 'nullable|string|max:255',
            'parent_guardian_region_code' => 'nullable|string',
            'parent_guardian_region_name' => 'nullable|string',
            'parent_guardian_province_code' => 'nullable|string',
            'parent_guardian_province_name' => 'nullable|string',
            'parent_guardian_city_code' => 'nullable|string',
            'parent_guardian_city_name' => 'nullable|string',
            'parent_guardian_barangay_code' => 'nullable|string',
            'parent_guardian_barangay_name' => 'nullable|string',
            'parent_guardian_street' => 'nullable|string|max:255',
            'parent_guardian_district' => 'nullable|string|max:255',
            
            'learner_classification' => 'nullable|array',
            'learner_classification.*' => 'string',
            'learner_classification_other' => 'nullable|string|max:255',
            
            // TWSP document validation
            'psa_birth_certificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'psa_marriage_contract' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'high_school_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'id_pictures_1x1.*' => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
            'id_pictures_passport.*' => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
            'government_school_id.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'certificate_of_indigency' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        // Remove TWSP document fields from main application update
        $twspDocumentFields = [
            'psa_birth_certificate',
            'psa_marriage_contract', 
            'high_school_document',
            'id_pictures_1x1',
            'id_pictures_passport',
            'government_school_id',
            'certificate_of_indigency'
        ];
        
        $applicationData = collect($validated)->except($twspDocumentFields)->toArray();

        // Define field labels for better display
        $fieldLabels = [
            'title_of_assessment_applied_for' => 'Title of Assessment',
            'photo' => 'Photo',
            'surname' => 'Surname',
            'firstname' => 'First Name',
            'middlename' => 'Middle Name',
            'middleinitial' => 'Middle Initial',
            'name_extension' => 'Name Extension',
            'region_name' => 'Region',
            'province_name' => 'Province',
            'city_name' => 'City',
            'barangay_name' => 'Barangay',
            'district' => 'District',
            'street_address' => 'Street Address',
            'zip_code' => 'Zip Code',
            'mothers_name' => "Mother's Name",
            'fathers_name' => "Father's Name",
            'sex' => 'Sex',
            'civil_status' => 'Civil Status',
            'mobile' => 'Mobile Number',
            'email' => 'Email Address',
            'highest_educational_attainment' => 'Educational Attainment',
            'employment_status' => 'Employment Status',
            'birthdate' => 'Birthdate',
            'birthplace' => 'Birthplace',
            'age' => 'Age',
            'birthplace_region' => 'Birthplace Region',
            'birthplace_province' => 'Birthplace Province',
            'birthplace_city' => 'Birthplace City',
            'parent_guardian_name' => 'Parent/Guardian Name',
            'parent_guardian_region_name' => 'Parent/Guardian Region',
            'parent_guardian_province_name' => 'Parent/Guardian Province',
            'parent_guardian_city_name' => 'Parent/Guardian City',
            'parent_guardian_barangay_name' => 'Parent/Guardian Barangay',
            'parent_guardian_street' => 'Parent/Guardian Street',
            'parent_guardian_district' => 'Parent/Guardian District',   
            'learner_classification' => 'Learner/Trainee/Student Classification',
        ];

        $applicationData['surname'] = strtoupper($applicationData['surname']);
        $applicationData['firstname'] = strtoupper($applicationData['firstname']);
        $applicationData['middlename'] = strtoupper($applicationData['middlename'] ?? '');
        $applicationData['middleinitial'] = strtoupper($applicationData['middleinitial'] ?? '');
        
        $learnerClassification = $request->input('learner_classification', []);
        if ($request->filled('learner_classification_other')) {
            $learnerClassification[] = $request->input('learner_classification_other');
        }
        $applicationData['learner_classification'] = $learnerClassification;

        // Track changes before updating
        $changedAt = now();
        $changes = [];

        // Track changes for application fields only
        foreach ($applicationData as $field => $newValue) {
            // Skip photo field for now (handle separately)
            if ($field === 'photo') {
                continue;
            }

            $oldValue = $application->$field;

            // Special handling for array fields like learner_classification
            if ($field === 'learner_classification') {
                $oldArray = is_array($oldValue) ? $oldValue : json_decode($oldValue, true) ?? [];
                $newArray = is_array($newValue) ? $newValue : [];
                
                // Compare arrays
                if (array_diff($oldArray, $newArray) || array_diff($newArray, $oldArray)) {
                    $changes[] = [
                        'application_id' => $application->id,
                        'field_name' => $field,
                        'field_label' => $fieldLabels[$field] ?? ucwords(str_replace('_', ' ', $field)),
                        'old_value' => implode(', ', $oldArray),
                        'new_value' => implode(', ', $newArray),
                        'changed_at' => $changedAt,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                continue;
            }

            // Check if value actually changed
            if ($oldValue != $newValue) {
                $changes[] = [
                    'application_id' => $application->id,
                    'field_name' => $field,
                    'field_label' => $fieldLabels[$field] ?? ucwords(str_replace('_', ' ', $field)),
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                    'changed_at' => $changedAt,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Handle photo upload
        if ($request->hasFile('photo')) {
            $oldPhotoPath = $application->photo;
            $photoPath = $request->file('photo')->store('photos');
            $applicationData['photo'] = $photoPath;

            // Track photo change
            $changes[] = [
                'application_id' => $application->id,
                'field_name' => 'photo',
                'field_label' => 'Photo',
                'old_value' => $oldPhotoPath ? 'Previous photo uploaded' : 'No photo',
                'new_value' => 'New photo uploaded',
                'changed_at' => $changedAt,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Delete old photo if exists
            if ($oldPhotoPath && \Storage::disk('public')->exists($oldPhotoPath)) {
                \Storage::disk('public')->delete($oldPhotoPath);
            }
        }

        // Handle TWSP document uploads (only for TWSP applications)
        if ($application->application_type === 'TWSP') {
            $twspDocument = $application->twspDocument;
            $twspUpdates = [];

            // Handle single file uploads
            $singleFiles = [
                'psa_birth_certificate' => 'PSA Birth Certificate',
                'psa_marriage_contract' => 'PSA Marriage Contract', 
                'high_school_document' => 'High School Document',
                'certificate_of_indigency' => 'Certificate of Indigency'
            ];

            foreach ($singleFiles as $field => $label) {
                if ($request->hasFile($field)) {
                    // Delete old file if exists
                    if ($twspDocument && $twspDocument->$field && \Storage::exists($twspDocument->$field)) {
                        \Storage::delete($twspDocument->$field);
                    }

                    // Store new file
                    $filePath = $request->file($field)->store('twsp_documents');
                    $twspUpdates[$field] = $filePath;

                    // Track change
                    $changes[] = [
                        'application_id' => $application->id,
                        'field_name' => $field,
                        'field_label' => $label,
                        'old_value' => $twspDocument && $twspDocument->$field ? 'Previous file uploaded' : 'No file',
                        'new_value' => 'New file uploaded',
                        'changed_at' => $changedAt,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            // Handle multiple file uploads
            $multipleFiles = [
                'id_pictures_1x1' => '1x1 ID Pictures',
                'id_pictures_passport' => 'Passport Size Pictures',
                'government_school_id' => 'Government/School ID'
            ];

            foreach ($multipleFiles as $field => $label) {
                if ($request->hasFile($field)) {
                    // Delete old files if exist
                    if ($twspDocument && $twspDocument->$field) {
                        foreach ($twspDocument->$field as $oldFile) {
                            if (\Storage::disk('public')->exists($oldFile)) {
                                \Storage::disk('public')->delete($oldFile);
                            }
                        }
                    }

                    // Store new files
                    $filePaths = [];
                    foreach ($request->file($field) as $file) {
                        $filePaths[] = $file->store('twsp_documents', 'public');
                    }
                    $twspUpdates[$field] = $filePaths;

                    // Track change
                    $changes[] = [
                        'application_id' => $application->id,
                        'field_name' => $field,
                        'field_label' => $label,
                        'old_value' => $twspDocument && $twspDocument->$field ? count($twspDocument->$field) . ' files' : 'No files',
                        'new_value' => count($filePaths) . ' new files uploaded',
                        'changed_at' => $changedAt,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            // Update or create TWSP document record
            if (!empty($twspUpdates)) {
                if ($twspDocument) {
                    $twspDocument->update($twspUpdates);
                } else {
                    $application->twspDocument()->create(array_merge($twspUpdates, [
                        'application_id' => $application->id
                    ]));
                }
            }
        }

        // Update application
        $application->update(array_merge($applicationData, [
            'status' => Application::STATUS_PENDING,
            'correction_requested' => false,
            'correction_message' => null,
            'correction_requested_at' => null,
            'was_corrected' => true,
            'resubmitted_at' => $changedAt,
        ]));
        $adminUsers = \App\Models\User::where('role', 'admin')->get();
        foreach ($adminUsers as $admin) {
            $admin->notify(new ApplicationResubmittedNotification($application));
        }

        // Insert all changes at once
        if (!empty($changes)) {
            \App\Models\Application\ApplicationChange::insert($changes);
        }

        return redirect()->route('applicant.dashboard')
            ->with('success', 'Application resubmitted successfully! Your changes have been recorded.');
    }


    public function uploadPaymentProof(Request $request, Application $application)
    {
        // Verify ownership
        abort_unless($application->user_id === auth()->id(), 403);
        
        // Validate
        $request->validate([
            'payment_proof' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048', // 2MB max
        ]);
        
        // Delete old payment proof if exists
        if ($application->payment_proof) {
            Storage::delete($application->payment_proof);
        }
        // Store new payment proof
        $path = $request->file('payment_proof')->store('payment-proofs');
        
        // Update application
        $application->update([
            'payment_proof' => $path,
            'payment_status' => Application::PAYMENT_STATUS_SUBMITTED,
            'payment_submitted_at' => now(),
        ]);
        
        return redirect()->route('applicant.dashboard')
            ->with('success', 'Payment proof uploaded successfully. Waiting for admin verification.');
    }
    public function submitReassessmentPayment(Request $request, Application $application)
    {
        // Verify ownership
        abort_unless($application->user_id === auth()->id(), 403);
        
        // Verify that application has failed assessment
        $assessmentResult = $application->assessmentResult;
        if (!$assessmentResult || $assessmentResult->result !== 'Not Yet Competent') {
            return redirect()->route('applicant.dashboard')
                ->with('error', 'This application is not eligible for reassessment.');
        }
        
        // Validate
        $request->validate([
            'payment_proof' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048', // 2MB max
            'payment_reference' => 'nullable|string|max:255',
        ]);
        
        // Determine if this is 1st or 2nd reassessment
        $failedCount = $application->assessmentResults()->where('result', 'Not Yet Competent')->count();
        $isSecondReassessment = $failedCount >= 2;
        
        if ($isSecondReassessment) {
            // This is the 2nd reassessment payment
            
            // Delete old payment proof if exists
            if ($application->second_reassessment_payment_proof) {
                Storage::disk('public')->delete($application->second_reassessment_payment_proof);
            }
            
            // Store new payment proof
            $path = $request->file('payment_proof')->store('reassessment-payments');
            
            // Update application with 2nd reassessment payment
            $application->update([
                'second_reassessment_payment_proof' => $path,
                'second_reassessment_payment_reference' => $request->input('payment_reference'),
                'second_reassessment_payment_status' => 'pending',
                'second_reassessment_payment_date' => now(),
            ]);
            
            return redirect()->route('applicant.dashboard')
                ->with('success', '2nd Reassessment payment submitted successfully. Waiting for admin verification.');
        } else {
            // This is the 1st reassessment payment
            
            // Delete old payment proof if exists
            if ($application->reassessment_payment_proof) {
                Storage::disk('public')->delete($application->reassessment_payment_proof);
            }
            
            // Store new payment proof
            $path = $request->file('payment_proof')->store('reassessment-payments');
            
            // Update application with 1st reassessment payment
            $application->update([
                'reassessment_payment_proof' => $path,
                'reassessment_payment_reference' => $request->input('payment_reference'),
                'reassessment_payment_status' => 'pending',
                'reassessment_payment_date' => now(),
            ]);
            
            return redirect()->route('applicant.dashboard')
                ->with('success', 'Reassessment payment submitted successfully. Waiting for admin verification.');
        }
    }


    
}
