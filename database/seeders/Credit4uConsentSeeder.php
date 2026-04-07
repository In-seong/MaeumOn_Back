<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ConsentTemplate;

class Credit4uConsentSeeder extends Seeder
{
    public function run(): void
    {
        ConsentTemplate::updateOrCreate(
            ['consent_type' => ConsentTemplate::TYPE_CREDIT4U],
            [
                'title' => '내보험다보여 정보이용 동의',
                'content' => '본인은 한국신용정보원의 내보험다보여 서비스를 통해 보험계약 정보를 조회하는 것에 동의합니다. 조회된 정보는 본 서비스의 보험정보 관리 목적으로만 활용되며, 제3자에게 제공되지 않습니다.',
                'is_active' => true,
            ]
        );
    }
}
