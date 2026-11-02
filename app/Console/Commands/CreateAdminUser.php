<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:create
                            {--name= : The name of the administrator}
                            {--email= : The email of the administrator}
                            {--username= : Optional unique username}
                            {--password= : The administrator password}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Securely create an initial or additional administrator user';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('--- Marina Aqua Administrator Provisioning ---');

        $name = $this->option('name') ?: $this->ask('Administrator Full Name');
        $email = $this->option('email') ?: $this->ask('Administrator Email');
        $username = $this->option('username') ?: $this->ask('Administrator Username (optional)');
        $password = $this->option('password') ?: $this->secret('Administrator Password (min 8 chars)');

        $data = [
            'name' => $name,
            'email' => $email,
            'username' => ! empty($username) ? $username : null,
            'password' => $password,
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'username' => ['nullable', 'string', 'max:100', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'username' => ! empty($username) ? $username : null,
            'password' => Hash::make($password),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->info("Administrator user [{$user->email}] successfully created with ID: {$user->id}.");

        return self::SUCCESS;
    }
}
