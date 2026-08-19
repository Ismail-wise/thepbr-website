<?php

namespace App\Filament\Resources\StudentEnrollments\Pages;

use App\Filament\Resources\StudentEnrollments\StudentEnrollmentResource;
use Filament\Resources\Pages\ListRecords;

class ListStudentEnrollments extends ListRecords
{
    protected static string $resource = StudentEnrollmentResource::class;
}
