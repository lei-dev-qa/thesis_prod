<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Application\Application;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class AssessmentBatchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $programs = [
            'VISUAL GRAPHIC DESIGN NC III' => 20,
            'BOOKKEEPING NC III' => 10,
            'EVENTS MANAGEMENT SERVICES NC III' => 30,
            'TOURISM PROMOTION SERVICES NC II' => 10,
            'PHARMACY SERVICES NC III' => 30,
        ];

        foreach ($programs as $program => $count) {
            echo "\n📦 Creating {$count} TWSP applicants for {$program}...\n";
            $this->createApplicantsForProgram($program, $count);
        }
    }

    private function createApplicantsForProgram($ncProgram, $numberOfApplicants)
    {
        // ✅ Better program code mapping
        $programCodes = [
            'VISUAL GRAPHIC DESIGN NC III' => 'vgd',
            'BOOKKEEPING NC III' => 'bkp',
            'EVENTS MANAGEMENT SERVICES NC III' => 'ems',
            'TOURISM PROMOTION SERVICES NC II' => 'tps',
            'PHARMACY SERVICES NC III' => 'phs',
        ];

        $programCode = $programCodes[$ncProgram] ?? 'app'; // fallback to 'app'

        for ($i = 1; $i <= $numberOfApplicants; $i++) {
            $user = User::create([
                'name' => strtoupper($programCode) . " Applicant {$i}",
                'email' => "{$programCode}.applicant{$i}@test.com",
                'password' => bcrypt('password123'),
                'role' => 'applicant',
                'email_verified_at' => now(),
            ]);

            $application = Application::create([
                'user_id' => $user->id,
                'application_type' => 'Assessment Only',  // TWSP only
                'reference_number' => $this->generateReferenceNumber(),
                'title_of_assessment_applied_for' => $ncProgram,
                'surname' => strtoupper($programCode) . "Surname{$i}",
                'firstname' => "Firstname{$i}",
                'middlename' => "Middlename{$i}",
                'middleinitial' => 'M',
                'region_code' => '01',
                'region_name' => 'Region I',
                'province_code' => '0101',
                'province_name' => 'Ilocos Norte',
                'city_code' => '010101',
                'city_name' => 'Laoag City',
                'barangay_code' => '010101001',
                'barangay_name' => 'Barangay ' . $i,
                'district' => '1st District',
                'street_address' => "Test Street {$i}",
                'zip_code' => '2900',
                'mothers_name' => "Mother {$i}",
                'fathers_name' => "Father {$i}",
                'sex' => $i % 2 == 0 ? 'male' : 'female',
                'civil_status' => 'Single',
                'mobile' => '09' . str_pad($i, 9, '0', STR_PAD_LEFT),
                'email' => $user->email,
                'birthdate' => Carbon::now()->subYears(20 + $i)->format('Y-m-d'),
                'birthplace' => 'Laoag City',
                'age' => 20 + $i,
                'highest_educational_attainment' => 'College Graduate',
                'employment_status' => 'Unemployed',
                'status' => 'approved',
                 'payment_status' => 'verified',
                'assessment_batch_id' => null, 
                'reviewed_by' => 1,
                'reviewed_at' => Carbon::now()->subDays(30),
                'review_remarks' => 'Approved',
                'training_status' => 'completed',
                'training_completed_at' => Carbon::now()->subDays(5 + $i),
                'training_remarks' => 'Completed',
            ]);

            // Add related records
            $application->workExperiences()->create([
                'company_name' => "Company {$i}",
                'position' => 'Staff',
                'date_from' => Carbon::now()->subYears(2),
                'date_to' => Carbon::now()->subYear(),
                'monthly_salary' => 15000,
                'appointment_status' => 'Full-time',
                'years_experience' => 1,
            ]);

            $application->trainings()->create([
                'title' => $ncProgram . ' Training',
                'venue' => 'TESDA Center',
                'date_from' => Carbon::now()->subMonths(3),
                'date_to' => Carbon::now()->subMonths(2),
                'hours' => 320,
                'conducted_by' => 'TESDA',
            ]);

            $application->competencyAssessments()->create([
                'title' => 'Basic Skills',
                'qualification_level' => 'NC II',
                'industry_sector' => 'General',
                'certificate_number' => strtoupper($programCode) . '-' . str_pad($i, 6, '0', STR_PAD_LEFT),
                'date_of_issuance' => Carbon::now()->subYear(),
                'expiration_date' => Carbon::now()->addYears(4),
            ]);

            echo "  ✓ {$user->email}\n";
        }
    }

    private function generateReferenceNumber(): string
    {
        do {
            $referenceNumber = str_pad(random_int(0, 999999999999999), 15, '0', STR_PAD_LEFT);
        } while (Application::where('reference_number', $referenceNumber)->exists());

        return $referenceNumber;
    }
}
