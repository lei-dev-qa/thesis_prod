<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Applicant\ApplicantController;
use App\Http\Controllers\Applicant\ApplicantEmploymentController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\ApplicationController;
use App\Http\Controllers\Admin\TrainingBatchController;
use App\Http\Controllers\Admin\TrainingScheduleController;
use App\Http\Controllers\Admin\AssessmentBatchController;
use App\Http\Controllers\Admin\ReassessmentController;
use App\Http\Controllers\Admin\TwspAnnouncementController;
use App\Http\Controllers\Admin\EmploymentFeedbackController;
use App\Http\Controllers\PDF\ApplicationFormPdfController;
use App\Http\Controllers\PDF\TwspDocumentPdfController;
use App\Http\Controllers\PDF\AttendancePdfController;
use App\Http\Controllers\ContactController;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/benefits', function () {
    return view('pages.benefits.index');
})->name('benefits');
Route::get('/howtoenroll', function () {
    return view('pages.howtoenroll.index');
})->name('howtoenroll');
Route::get('/contact', function () {
    return view('pages.contact.index');
})->name('contact');
Route::post('/contact', [ContactController::class, 'submit'])->name('contact.submit');
Route::get('/programs', function () {
    return view('pages.programs.index');
})->name('programs');

// Authentication Routes
Route::middleware('guest')->group(function (){
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    //Google OAuth Routes
    Route::get('/auth/google', [AuthController::class, 'redirectToGoogle'])->name('auth.google');
    Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);
});

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Admin Routes
    Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/calendar', [AdminController::class, 'calendar'])->name('calendar.index');
        Route::get('/analytics/volume-data', [AdminController::class, 'getVolumeAnalyticsData'])
            ->name('analytics.volume-data');


        Route::patch('/applications/{application}/update-reference', [ApplicationController::class, 'updateReference']
            )->name('applications.update-reference');

        
        // Training Batch management routes
        Route::prefix('training-batches')->name('training-batches.')->group(function () {
            Route::get('/', [TrainingBatchController::class, 'index'])->name('index');
            Route::get('/{batch}', [TrainingBatchController::class, 'show'])->name('show');
            Route::post('/{batch}/complete', [TrainingBatchController::class, 'complete'])->name('complete');
            Route::post('/{batch}/add-applicant', [TrainingBatchController::class, 'addApplicant'])->name('add-applicant');
            Route::delete('/{batch}/remove-applicant/{application}', [TrainingBatchController::class, 'removeApplicant'])->name('remove-applicant');
            
            Route::post('/applications/bulk-complete', [TrainingBatchController::class, 'bulkComplete'])->name('bulk-complete');
            Route::post('/applications/bulk-fail', [TrainingBatchController::class, 'bulkFail'])->name('bulk-fail');

            Route::get('/progress/list', [TrainingBatchController::class, 'progress'])->name('progress');
            Route::get('/history/list', [TrainingBatchController::class, 'history'])->name('history');
            Route::get('/history/{batch}', [TrainingBatchController::class, 'historyBatch'])->name('history.batch');
        });

        // Training Schedule management routes
        Route::prefix('training-schedules')->name('training-schedules.')->group(function () {
            Route::get('/', [TrainingScheduleController::class, 'index'])->name('index');
            Route::post('/', [TrainingScheduleController::class, 'store'])->name('store');
            Route::put('/{schedule}', [TrainingScheduleController::class, 'update'])->name('update');
            Route::delete('/{schedule}', [TrainingScheduleController::class, 'destroy'])->name('destroy');
            Route::get('/{schedule}/edit', [TrainingScheduleController::class, 'edit'])->name('edit');
            Route::get('/{schedule}/debug', [TrainingScheduleController::class, 'debug'])->name('debug');
            Route::post('/{schedule}/fix-links', [TrainingScheduleController::class, 'fixApplicationLinks'])->name('fix-links');
            Route::post('/{schedule}/send-schedule', [TrainingScheduleController::class, 'sendNotifications'])->name('send-schedule');
        });
            // Assessment Batch management routes
        Route::resource('assessment-batches', AssessmentBatchController::class)
            ->only(['index', 'create', 'store', 'show', 'update', 'destroy']);
        
        Route::prefix('assessment-batches')->name('assessment-batches.')->group(function () {
            
            Route::post('{assessment_batch}/add-applicants', [AssessmentBatchController::class, 'addApplicants'])
                ->name('add-applicants');

            Route::delete('{assessment_batch}/applications/{application}', [AssessmentBatchController::class, 'unassignApplicant'])
                ->name('unassign-applicant');

            Route::post('{assessment_batch}/applications/{application}/result', [AssessmentBatchController::class, 'markAssessmentCompleted'])
                ->name('mark-completed');

            Route::post('{assessment_batch}/close', [AssessmentBatchController::class, 'close'])
                ->name('close');

            Route::get('history/list', [AssessmentBatchController::class, 'history'])
                ->name('history');
            Route::post('/{assessment_batch}/send-schedule', [AssessmentBatchController::class, 'sendScheduleNotifications'])
                ->name('send-schedule');
        });

        Route::get('/assessment-batches/{assessment_batch}/attendance-pdf', 
            [AttendancePdfController::class, 'generate']
        )->name('assessment-batches.attendance-pdf');


        // Applicants & Applications management routes
        Route::get('/applicants', [AdminController::class, 'indexApplicants'])->name('applicants.index');

        // Application Management routes
        Route::resource('applications', ApplicationController::class)->only(['index', 'show']);
        Route::prefix('applications')->name('applications.')->group(function () {
            Route::post('{application}/approve', [ApplicationController::class, 'approveApplication'])
                ->name('approve');
            Route::post('{application}/reject', [ApplicationController::class, 'rejectApplication'])
                ->name('reject');
            Route::post('{application}/request-correction', [ApplicationController::class, 'requestCorrection'])
                ->name('request-correction');
            Route::get('{application}/print-pdf', [ApplicationFormPdfController::class, 'print'])
                ->name('print_pdf');
            Route::get('{application}/twsp-documents-pdf', [TwspDocumentPdfController::class, 'generate'])
                ->name('twsp_documents_pdf');

        });
        // Payment verification routes (outside applications prefix to avoid naming conflicts)
        Route::post('/payment/{application}/verify', [ApplicationController::class, 'verifyPayment'])
            ->name('payment.verify');
        Route::post('/payment/{application}/reject', [ApplicationController::class, 'rejectPayment'])
            ->name('payment.reject');
        
        // Official receipt upload routes
        Route::post('/payment/{application}/upload-official-receipt', [ApplicationController::class, 'uploadOfficialReceipt'])
            ->name('payment.upload-official-receipt');
        Route::post('/payment/{application}/upload-reassessment-official-receipt', [ApplicationController::class, 'uploadReassessmentOfficialReceipt'])
            ->name('payment.upload-reassessment-official-receipt');
        Route::post('/payment/{application}/upload-second-reassessment-official-receipt', [ApplicationController::class, 'uploadSecondReassessmentOfficialReceipt'])
            ->name('payment.upload-second-reassessment-official-receipt');

        Route::get('/application/history', [AdminController::class, 'listApplicationsHistory'])->name('history.index');

        // contact message
        Route::get('/reassessment/payments', [ReassessmentController::class, 'index'])
            ->name('reassessment.index');
        Route::post('/reassessment/{application}/verify-payment', [ReassessmentController::class, 'verifyPayment'])
            ->name('reassessment.verify-payment');
        Route::post('/reassessment/{application}/upload-official-receipt', [ReassessmentController::class, 'uploadReassessmentOfficialReceipt'])
            ->name('reassessment.upload-official-receipt');
        Route::post('/reassessment/{application}/upload-second-official-receipt', [ReassessmentController::class, 'uploadSecondReassessmentOfficialReceipt'])
            ->name('reassessment.upload-second-official-receipt');
            
        Route::get('/twsp-announcements', [TwspAnnouncementController::class, 'index'])->name('twsp.index');
        Route::post('/twsp-announcements', [TwspAnnouncementController::class, 'store'])->name('twsp.store');
        Route::post('/twsp-announcements/{id}/close', [TwspAnnouncementController::class, 'close'])->name('twsp.close');
        
        Route::get('/contact-messages', [ContactController::class, 'index'])->name('contact.messages');
        Route::patch('/contact-messages/{id}/mark-read', [ContactController::class, 'markAsRead'])
            ->name('contact.mark-read');
        Route::delete('/contact-messages/{id}', [ContactController::class, 'destroy'])
            ->name('contact.destroy');

        // Employment Feedback routes
        Route::get('/employment-feedback', [App\Http\Controllers\Admin\EmploymentFeedbackController::class, 'index'])
            ->name('employment-feedback.index');
        Route::get('/employment-feedback/{batch}', [App\Http\Controllers\Admin\EmploymentFeedbackController::class, 'show'])
            ->name('employment-feedback.show');
        Route::post('/employment-feedback/application/{application}', [App\Http\Controllers\Admin\EmploymentFeedbackController::class, 'store'])
            ->name('employment-feedback.store');
        Route::put('/employment-feedback/record/{employmentRecord}', [App\Http\Controllers\Admin\EmploymentFeedbackController::class, 'update'])
            ->name('employment-feedback.update');
            // routes/web.php (add this to your admin routes)
        Route::post('/employment-feedback/{employmentRecord}/mark-viewed', [EmploymentFeedbackController::class, 'markViewed'])->name('admin.employment-feedback.mark-viewed');

    });
    Route::middleware(['auth'])->group(function () {
        Route::get('/applications/{application}/files/{fileType}', [ApplicationController::class, 'serveFile'])
            ->name('applications.file')
            ->where('fileType', 'payment-proof|official-receipt|reassessment-receipt|second-reassessment-receipt');
    });

    // Applicant Routes
    Route::middleware(['auth', 'role:applicant'])->prefix('applicant')->name('applicant.')->group(function (){
        Route::get('/dashboard', [ApplicantController::class, 'dashboard'])->name('dashboard');
    
        Route::get('/apply', [ApplicantController::class, 'create'])->name('apply.create');
        Route::post('/apply', [ApplicantController::class, 'store'])->name('apply.store');
        Route::get('/applications/{application}', [ApplicantController::class, 'show'])->name('applications.show')->whereNumber('application');
        Route::post('/payment/{application}', [ApplicantController::class, 'uploadPaymentProof'])
            ->name('payment.upload');
        
        Route::get('/applications/{application}/edit', [ApplicantController::class, 'edit'])
            ->name('applications.edit');
        Route::put('/applications/{application}', [ApplicantController::class, 'update'])
            ->name('applications.update');

        Route::post('/applications/{application}/reassessment', [ApplicantController::class, 'submitReassessmentPayment'])->name('reassessment.submit');
    
        Route::post('/employment-feedback/{application}', [ApplicantEmploymentController::class, 'store'])
            ->name('employment-feedback.store');
        
        Route::get('/applications/{application}/files/{fileType}', [ApplicationController::class, 'serveFile'])
            ->name('applications.file')
            ->where('fileType', 'payment-proof|official-receipt|reassessment-receipt|second-reassessment-receipt');

});