<?php

namespace Database\Seeders;

use App\Models\InsuranceCompany;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class InsuranceDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 관리자 계정 생성
        User::create([
            'name' => '관리자',
            'email' => 'admin@mauemon.com',
            'password' => Hash::make('password'),
            'phone' => '010-1234-5678',
            'role' => 'admin',
        ]);

        // 테스트 사용자 생성
        User::create([
            'name' => '홍길동',
            'email' => 'user@mauemon.com',
            'password' => Hash::make('password'),
            'phone' => '010-9876-5432',
            'role' => 'user',
        ]);

        // 보험사 데이터
        $insuranceCompanies = [
            [
                'name' => '삼성화재',
                'code' => 'SAMSUNG',
                'fax_number' => '1588-5114',
                'is_active' => true,
            ],
            [
                'name' => '현대해상',
                'code' => 'HYUNDAI',
                'fax_number' => '1588-5656',
                'is_active' => true,
            ],
            [
                'name' => 'DB손해보험',
                'code' => 'DB',
                'fax_number' => '1588-0100',
                'is_active' => true,
            ],
            [
                'name' => 'KB손해보험',
                'code' => 'KB',
                'fax_number' => '1544-0114',
                'is_active' => true,
            ],
            [
                'name' => '메리츠화재',
                'code' => 'MERITZ',
                'fax_number' => '1566-7711',
                'is_active' => true,
            ],
            [
                'name' => '한화손해보험',
                'code' => 'HANWHA',
                'fax_number' => '1566-8000',
                'is_active' => true,
            ],
            [
                'name' => '롯데손해보험',
                'code' => 'LOTTE',
                'fax_number' => '1588-3344',
                'is_active' => true,
            ],
            [
                'name' => '흥국화재',
                'code' => 'HEUNGKUK',
                'fax_number' => '1688-1688',
                'is_active' => true,
            ],
        ];

        foreach ($insuranceCompanies as $company) {
            InsuranceCompany::create($company);
        }
    }
}
