<?php

namespace App\Mail;

use App\Models\Student;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;

class LoginAlertMail extends Mailable implements ShouldQueue
{
    public Student $student;

    public function __construct(Student $student)
    {
        $this->student = $student;
    }

    public function build()
    {
        return $this->subject('Login Alert')
                    ->view('emails.login-alert');
    }
}