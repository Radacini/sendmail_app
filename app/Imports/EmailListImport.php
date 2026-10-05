<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class EmailListImport implements ToModel, WithHeadingRow
{
    protected $emails = [];

    public function model(array $row)
    {
        $email = trim($row['email'] ?? $row['email_address'] ?? $row['mail'] ?? '');
        $name = trim($row['name'] ?? $row['full_name'] ?? $row['first_name'] ?? '');

        if (!empty($email)) {
            $this->emails[] = [
                'email' => $email,
                'name' => $name,
            ];
        }

        return null; // We'll handle insertion manually
    }

    public function getEmails()
    {
        return $this->emails;
    }
}
