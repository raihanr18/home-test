<?php

namespace App\Console\Commands;

use App\Models\Student;
use Illuminate\Console\Command;

class VerifyDataCommand extends Command
{
    protected $signature = 'students:verify';
    protected $description = 'Verify imported student data';

    public function handle()
    {
        $this->info("Total students: " . Student::count());
        $this->newLine();

        $this->info("First 5 students with leading zero NIMs:");
        $students = Student::where('nim', 'LIKE', '0%')->orderBy('nim')->take(5)->get();

        foreach ($students as $student) {
            $this->line(sprintf("  - %s | NIM: %s | Birth: %s", 
                $student->nama, 
                $student->nim, 
                $student->tanggal_lahir->format('Y-m-d')
            ));
        }

        $this->newLine();
        $this->info("Random sample of 5 students:");
        $students = Student::inRandomOrder()->take(5)->get();

        foreach ($students as $student) {
            $this->line(sprintf("  - %s | NIM: %s | Birth: %s", 
                $student->nama, 
                $student->nim, 
                $student->tanggal_lahir->format('Y-m-d')
            ));
        }

        return 0;
    }
}
