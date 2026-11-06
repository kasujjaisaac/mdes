<?php

namespace Database\Seeders;

use App\Models\Diocese;
use App\Models\District;
use App\Models\EducationCluster;
use App\Models\Parish;
use App\Models\SubCounty;
use Illuminate\Database\Seeder;

class DiocesanStructureSeeder extends Seeder
{
    public function run(): void
    {
        $diocese = Diocese::query()->updateOrCreate(
            ['code' => 'MD'],
            [
                'name' => 'Masaka Diocese',
                'email' => 'education@masakadiocese.test',
                'telephone' => '+256700000001',
                'address' => 'Masaka, Uganda',
                'is_active' => true,
            ],
        );

        $district = District::query()->updateOrCreate(
            ['name' => 'Masaka'],
            ['code' => 'MSK', 'is_active' => true],
        );

        SubCounty::query()->updateOrCreate(
            ['district_id' => $district->id, 'name' => 'Kimanya-Kabonera'],
            ['code' => 'MSK-KK', 'is_active' => true],
        );

        EducationCluster::query()->updateOrCreate(
            ['name' => 'Central education cluster'],
            ['code' => 'CEC', 'is_active' => true],
        );

        foreach ([
            ['name' => 'kitovu parish', 'code' => 'KIT'],
            ['name' => 'bwanda parish', 'code' => 'BWA'],
            ['name' => 'villa maria parish', 'code' => 'VIM'],
        ] as $parish) {
            Parish::query()->updateOrCreate(
                ['code' => $parish['code']],
                [
                    'diocese_id' => $diocese->id,
                    'name' => $parish['name'],
                    'is_active' => true,
                ],
            );
        }
    }
}
