<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create
                            {email : The email address of the user}
                            {password? : The password for the user (prompted securely if omitted)}
                            {--name= : The display name (defaults to a name derived from the email)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new user for the mail sending application';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');
        $password = $this->argument('password') ?? $this->secret('Password');

        // Validate email format
        $validator = Validator::make(['email' => $email], [
            'email' => 'required|email|unique:users,email',
        ]);

        if ($validator->fails()) {
            $this->error('Invalid email address or email already exists.');
            return Command::FAILURE;
        }

        // Validate password
        if (strlen($password) < 6) {
            $this->error('Password must be at least 6 characters long.');
            return Command::FAILURE;
        }

        $name = $this->option('name');

        if (!$name) {
            // Extract name from email
            $name = explode('@', $email)[0];
            $name = str_replace('.', ' ', $name);
            $name = ucwords($name);
        }

        // Create the user
        try {
            User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
            ]);

            $this->info("User created successfully!");
            $this->info("Name: {$name}");
            $this->info("Email: {$email}");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to create user: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
