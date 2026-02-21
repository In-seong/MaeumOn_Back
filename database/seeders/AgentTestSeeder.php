<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Agent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AgentTestSeeder extends Seeder
{
    /**
     * 테스트용 설계사 계정 생성
     *
     * 사용법: php artisan db:seed --class=AgentTestSeeder
     */
    public function run(): void
    {
        // 이미 존재하면 건너뛰기
        if (Account::where('username', 'agent1')->exists()) {
            $this->command->info('agent1 계정이 이미 존재합니다.');
            return;
        }

        $account = Account::create([
            'username' => 'agent1',
            'password_hash' => Hash::make('agent1234'),
            'role' => Account::ROLE_AGENT,
            'is_active' => true,
        ]);

        Agent::create([
            'agent_id' => 'AGT00001',
            'account_id' => $account->account_id,
            'name' => '김설계',
            'phone' => '010-1234-5678',
            'email' => 'agent@test.com',
            'office_location' => '마음온 보험대리점',
            'is_active' => true,
        ]);

        $this->command->info('테스트 설계사 계정 생성 완료: agent1 / agent1234');
    }
}
