<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('public website uses working conversion links without contact placeholders', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('register'), false)
        ->assertSee(route('about'), false)
        ->assertSee(route('login'), false)
        ->assertSee(route('student.register'), false)
        ->assertDontSee('Contact Us')
        ->assertDontSee('Facebook Page')
        ->assertDontSee('Viber');

    $this->get(route('classes'))
        ->assertOk()
        ->assertSee(route('articles.index'), false)
        ->assertSee(route('student.register'), false)
        ->assertSee(route('login'), false)
        ->assertDontSee('Facebook Page')
        ->assertDontSee('Contact Us');
});

test('about page does not repeat the homepage instructor profile or expose sample data', function () {
    $this->get(route('about'))
        ->assertOk()
        ->assertDontSee('Sample Instructor Photo')
        ->assertDontSee('နမူနာ ဆရာအမည်')
        ->assertDontSee('Sample Instructor');
});

test('public blade views contain no dead hash links', function () {
    $views = [
        resource_path('views/layouts/site.blade.php'),
        resource_path('views/home.blade.php'),
        resource_path('views/about.blade.php'),
        resource_path('views/classes.blade.php'),
        resource_path('views/articles/show.blade.php'),
    ];

    foreach ($views as $view) {
        expect(file_get_contents($view))
            ->not->toContain('href="#"');
    }
});
