<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Application\Application;
use App\Models\User;
use App\Models\Training\TrainingBatch;
use App\Models\Training\TrainingResult;
use App\Models\TWSP\TwspAnnouncement;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;

class TraineeSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user if not exists (for reviewed_by field)
        $admin = User::firstOrCreate(
            ['email' => 'admin1@tesda.local'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        // Get existing active TWSP announcement
        $announcement = TwspAnnouncement::where('is_active', true)->first();
        
        if (!$announcement) {
            $this->command->error('❌ No active TWSP announcement found!');
            return;
        }

        // Create or get training batch for BOOKKEEPING NC III
        $trainingBatch = TrainingBatch::firstOrCreate(
            [
                'nc_program' => 'BOOKKEEPING NC III',
                'batch_number' => 1,
            ],
            [
                'max_students' => 24,
                'status' => TrainingBatch::STATUS_ENROLLING,
                'remarks' => 'Seeded batch for testing',
            ]
        );

        $filipinoFirstNames = [
            'Juan', 'Maria', 'Jose', 'Ana', 'Pedro', 'Rosa', 'Miguel', 'Carmen',
            'Luis', 'Sofia', 'Carlos', 'Isabel', 'Ramon', 'Elena', 'Antonio',
            'Luz', 'Manuel', 'Teresa', 'Ricardo', 'Patricia', 'Fernando', 'Gloria',
            'Roberto', 'Angelica', 'Eduardo'
        ];

        $filipinoLastNames = [
            'Santos', 'Reyes', 'Cruz', 'Bautista', 'Garcia', 'Mendoza', 'Torres',
            'Flores', 'Rivera', 'Gonzales', 'Ramos', 'Dela Cruz', 'Villanueva',
            'Castillo', 'Aquino', 'Fernandez', 'Valdez', 'Santiago', 'Morales',
            'Pascual', 'Domingo', 'Mercado', 'Aguilar', 'Navarro', 'Lopez'
        ];

        // Create 24 trainees (since you already have 1)
        for ($i = 1; $i <= 24; $i++) {
            // Create applicant user
            $applicant = User::create([
                'name' => $filipinoFirstNames[array_rand($filipinoFirstNames)] . ' ' . 
                          $filipinoLastNames[array_rand($filipinoLastNames)],
                'email' => 'traineex' . $i . '@example.com',
                'password' => Hash::make('password'),
                'role' => 'applicant',
                'email_verified_at' => now(),
            ]);

            $firstName = $filipinoFirstNames[array_rand($filipinoFirstNames)];
            $lastName = $filipinoLastNames[array_rand($filipinoLastNames)];
            $middleName = $filipinoLastNames[array_rand($filipinoLastNames)];

            // Create approved application with enrolled training status
            $application = Application::create([
                'user_id' => $applicant->id,
                'title_of_assessment_applied_for' => 'BOOKKEEPING NC III',
                'application_type' => 'TWSP',
                'surname' => $lastName,
                'firstname' => $firstName,
                'middlename' => $middleName,
                'middleinitial' => strtoupper(substr($middleName, 0, 1)),
                'name_extension' => null,
                'region_code' => '01',
                'region_name' => 'Region I - Ilocos Region',
                'province_code' => '0128',
                'province_name' => 'Ilocos Norte',
                'city_code' => '012801',
                'city_name' => 'Laoag City',
                'barangay_code' => '012801' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'barangay_name' => 'Barangay ' . $i,
                'district' => '1st District',
                'street_address' => fake()->streetAddress(),
                'zip_code' => '2900',
                'mothers_name' => fake()->name('female'),
                'fathers_name' => fake()->name('male'),
                'sex' => fake()->randomElement(['male', 'female']),
                'civil_status' => fake()->randomElement(['Single', 'Married', 'Widowed']),
                'mobile' => '09' . fake()->numerify('#########'),
                'email' => $applicant->email,
                'highest_educational_attainment' => fake()->randomElement([
                    'High School Graduate',
                    'Senior High School Graduate',
                    'College Level',
                    'College Graduate',
                    'Vocational Graduate'
                ]),
                'employment_status' => fake()->randomElement(['Employed', 'Unemployed', 'Self-employed']),
                'birthdate' => fake()->dateTimeBetween('-45 years', '-18 years')->format('Y-m-d'),
                'birthplace' => fake()->city() . ', Ilocos Norte',
                'age' => fake()->numberBetween(18, 45),
                
                // Approved and enrolled status
                'status' => Application::STATUS_APPROVED,
                'training_status' => Application::TRAINING_STATUS_ENROLLED,
                'training_batch_id' => $trainingBatch->id,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now()->subDays(rand(1, 30)),
                'review_remarks' => 'Application approved for training enrollment.',
            ]);

            // Increment filled slots since this is an approved TWSP application
            $announcement->increment('filled_slots');
            
            // If all slots are filled, set announcement to inactive
            if ($announcement->filled_slots >= $announcement->total_slots) {
                $announcement->update(['is_active' => false]);
            }

            // Add work experience
            $application->workExperiences()->create([
                'company_name' => fake()->company(),
                'position' => fake()->randomElement([
                    'Accounting Clerk',
                    'Bookkeeper Assistant',
                    'Office Staff',
                    'Administrative Assistant',
                    'Data Encoder'
                ]),
                'date_from' => Carbon::now()->subYears(rand(2, 5)),
                'date_to' => Carbon::now()->subMonths(rand(1, 12)),
                'monthly_salary' => rand(12000, 25000),
                'appointment_status' => fake()->randomElement(['Full-time', 'Part-time', 'Contractual']),
                'years_experience' => rand(1, 5),
            ]);

            // Add training
            $application->trainings()->create([
                'title' => fake()->randomElement([
                    'Basic Accounting',
                    'Computer Literacy',
                    'Office Administration',
                    'Financial Management'
                ]),
                'venue' => 'TESDA Training Center',
                'date_from' => Carbon::now()->subMonths(rand(6, 24)),
                'date_to' => Carbon::now()->subMonths(rand(3, 18)),
                'hours' => rand(40, 120),
                'conducted_by' => 'TESDA',
            ]);

            // Add licensure exam (optional)
            if (rand(0, 1)) {
                $application->licensureExams()->create([
                    'title' => 'Bookkeeping NC II',
                    'year_taken' => rand(2020, 2024),
                    'exam_venue' => 'TESDA Assessment Center',
                    'rating' => 'Passed',
                    'remarks' => 'Competent',
                    'expiry_date' => Carbon::now()->addYears(5),
                ]);
            }

            // Add competency assessment (optional)
            if (rand(0, 1)) {
                $application->competencyAssessments()->create([
                    'title' => 'Bookkeeping NC II',
                    'qualification_level' => 'NC II',
                    'industry_sector' => 'Business and Management',
                    'certificate_number' => strtoupper(fake()->bothify('BK-####-???-####')),
                    'date_of_issuance' => Carbon::now()->subYears(rand(1, 3)),
                    'expiration_date' => Carbon::now()->addYears(rand(2, 5)),
                ]);
            }

            // Create training result record
            TrainingResult::create([
                'application_id' => $application->id,
                'training_batch_id' => $trainingBatch->id,
                'result' => TrainingResult::RESULT_ONGOING,
            ]);
        }

        $announcement->refresh();
        $remainingSlots = $announcement->total_slots - $announcement->filled_slots;

        $this->command->info('✅ Successfully created 24 additional trainees for BOOKKEEPING NC III!');
        $this->command->info('📧 Login credentials: trainee1@example.com to trainee24@example.com');
        $this->command->info('🔑 Password: password');
        $this->command->info('📊 TWSP Announcement - Filled: ' . $announcement->filled_slots . '/' . $announcement->total_slots);
        $this->command->info('📊 Remaining slots: ' . $remainingSlots);
        $this->command->info('🔔 Announcement active: ' . ($announcement->is_active ? 'Yes' : 'No'));
    }
}
