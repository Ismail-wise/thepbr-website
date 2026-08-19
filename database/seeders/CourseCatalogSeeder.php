<?php

namespace Database\Seeders;

use App\Models\ChapterTool;
use App\Models\CourseChapter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CourseCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $version = config('pbr_course.version', 'v1');
        $chapters = config('pbr_course.chapters', []);

        if (count($chapters) !== 10) {
            throw new RuntimeException(
                'PBR catalog must contain exactly 10 chapters.'
            );
        }

        DB::transaction(function () use ($version, $chapters) {
            foreach ($chapters as $chapterData) {
                $chapter = CourseChapter::updateOrCreate(
                    [
                        'chapter_number' => $chapterData['number'],
                        'version' => $version,
                    ],
                    [
                        'slug' => $chapterData['slug'],
                        'phase' => $chapterData['phase'],
                        'title_en' => $chapterData['title_en'],
                        'title_mm' => $chapterData['title_mm'],
                        'description' => $chapterData['description'] ?? null,
                        'topics' => $chapterData['topics'] ?? null,
                        'is_published' => true,
                    ]
                );

                foreach ($chapterData['tools'] as $index => $toolData) {
                    ChapterTool::updateOrCreate(
                        [
                            'course_chapter_id' => $chapter->id,
                            'tool_key' => $toolData['key'],
                            'version' => $version,
                        ],
                        [
                            'slug' => $toolData['slug'],
                            'title_en' => $toolData['title'],
                            'title_mm' => $toolData['title_mm'] ?? null,
                            'tool_type' => $toolData['type'],
                            'description' => $toolData['description'] ?? null,
                            'sort_order' => $index + 1,
                            'supports_new_business' =>
                                $toolData['new'] ?? true,
                            'supports_existing_business' =>
                                $toolData['existing'] ?? true,
                            'is_published' => true,
                        ]
                    );
                }
            }
        });
    }
}
