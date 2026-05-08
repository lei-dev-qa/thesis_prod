<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application\Application;
use Illuminate\Http\Request;
use App\Notifications\Payment\PaymentVerifiedNotification;

class ReassessmentController extends Controller
{
    public function index()
    {
        // Get both 1st and 2nd reassessment pending payments
        $pendingPayments = Application::where(function($query) {
                $query->where('reassessment_payment_status', 'pending')
                    ->orWhere('second_reassessment_payment_status', 'pending');
            })
            ->with(['user', 'assessmentResult.cocResults'])
            ->orderBy('updated_at', 'desc')
            ->get();

        // Get both 1st and 2nd reassessment verified payments
        $verifiedPayments = Application::where(function($query) {
                $query->where('reassessment_payment_status', 'verified')
                    ->orWhere('second_reassessment_payment_status', 'verified');
            })
            ->whereNull('assessment_batch_id') // Not yet assigned to batch
            ->with(['user', 'assessmentResult.cocResults'])
            ->orderBy('updated_at', 'desc')
            ->get();

        // Get both 1st and 2nd reassessment rejected payments
        $rejectedPayments = Application::where(function($query) {
                $query->where('reassessment_payment_status', 'rejected')
                    ->orWhere('second_reassessment_payment_status', 'rejected');
            })
            ->with('user')
            ->orderBy('updated_at', 'desc')
            ->get();

        // Count both 1st and 2nd reassessment payments this month
        $monthlyTotal = Application::where(function($query) {
                $query->whereNotNull('reassessment_payment_status')
                    ->orWhereNotNull('second_reassessment_payment_status');
            })
            ->where(function($query) {
                $query->where(function($q) {
                    $q->whereMonth('reassessment_payment_date', now()->month)
                    ->whereYear('reassessment_payment_date', now()->year);
                })
                ->orWhere(function($q) {
                    $q->whereMonth('second_reassessment_payment_date', now()->month)
                    ->whereYear('second_reassessment_payment_date', now()->year);
                });
            })
            ->count();

        return view('admin.reassessment.index', compact(
            'pendingPayments',
            'verifiedPayments',
            'rejectedPayments',
            'monthlyTotal'
        ));
    }


   public function verifyPayment(Request $request, $applicationId)
    {
        $application = Application::findOrFail($applicationId);
        $action = $request->input('action');
        
        if ($application->hasFailedTwice()) {
            return back()->with('error', 'This applicant has reached the maximum reassessment attempts. Please contact admin.');
        }
        
        if ($action === 'verify') {
            // Check if this is a 2nd reassessment payment
            $isSecondReassessment = $application->second_reassessment_payment_proof !== null;
            
            if ($isSecondReassessment) {
                $application->update([
                    'second_reassessment_payment_status' => 'verified',
                    'assessment_status' => Application::ASSESSMENT_STATUS_PENDING,
                    'assessment_batch_id' => null,
                ]);
            } else {
                $application->update([
                    'reassessment_payment_status' => 'verified',
                    'assessment_status' => Application::ASSESSMENT_STATUS_PENDING,
                    'assessment_batch_id' => null,
                ]);
            }
            
            $application->user->notify(new PaymentVerifiedNotification($application, true));

            return back()->with('success', 'Payment verified successfully. Applicant can now be assigned to an assessment batch.');
        } elseif ($action === 'reject') {
            // Check if this is a 2nd reassessment payment
            $isSecondReassessment = $application->second_reassessment_payment_proof !== null;
            
            if ($isSecondReassessment) {
                $application->update([
                    'second_reassessment_payment_status' => 'rejected',
                ]);
            } else {
                $application->update([
                    'reassessment_payment_status' => 'rejected',
                ]);
            }
            
            return back()->with('success', 'Payment rejected. Applicant will need to resubmit payment.');
        }    

        return back()->with('error', 'Invalid action.');
    }

    public function uploadReassessmentOfficialReceipt(Request $request, $applicationId)
    {
        $application = Application::findOrFail($applicationId);
        
        $request->validate([
            'reassessment_official_receipt_photo' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        // Delete old receipt if exists
        if ($application->reassessment_official_receipt_photo) {
            \Storage::disk('public')->delete($application->reassessment_official_receipt_photo);
        }

        $path = $request->file('reassessment_official_receipt_photo')->store('official-receipts/reassessment');

        $application->update([
            'reassessment_official_receipt_photo' => $path,
            'reassessment_official_receipt_uploaded_at' => now(),
        ]);

        return back()->with('success', 'Reassessment official receipt uploaded successfully.');
    }

    public function uploadSecondReassessmentOfficialReceipt(Request $request, $applicationId)
    {
        $application = Application::findOrFail($applicationId);
        
        $request->validate([
            'second_reassessment_official_receipt_photo' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        // Delete old receipt if exists
        if ($application->second_reassessment_official_receipt_photo) {
            \Storage::disk('public')->delete($application->second_reassessment_official_receipt_photo);
        }

        $path = $request->file('second_reassessment_official_receipt_photo')->store('official-receipts/second-reassessment');

        $application->update([
            'second_reassessment_official_receipt_photo' => $path,
            'second_reassessment_official_receipt_uploaded_at' => now(),
        ]);

        return back()->with('success', '2nd Reassessment official receipt uploaded successfully.');
    }

}
