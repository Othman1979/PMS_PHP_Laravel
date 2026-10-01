<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormDialogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([DatabaseSeeder::class, DemoSeeder::class]);
    }

    private function admin(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    public function test_form_renders_without_app_shell_inside_dialog(): void
    {
        $this->actingAs($this->admin())->get('/departments/create?dialog=1')
            ->assertOk()
            ->assertSee('class="in-dialog"', false)
            ->assertDontSee('id="navPane"', false);

        $this->actingAs($this->admin())->get('/departments/create')
            ->assertOk()
            ->assertSee('id="navPane"', false);
    }

    public function test_successful_dialog_submit_hands_redirect_to_parent_page(): void
    {
        $this->actingAs($this->admin())->post('/departments', [
            '_dialog' => '1',
            'name_en' => 'Bakery',
            'name_ar' => 'المخبز',
            'is_active' => '1',
        ])
            ->assertOk()
            ->assertViewIs('dialog.close')
            ->assertViewHas('url', route('departments.index'))
            ->assertSessionHas('ok');
    }

    public function test_failed_dialog_submit_stays_in_dialog(): void
    {
        $this->actingAs($this->admin())
            ->from('/departments/create?dialog=1')
            ->post('/departments', ['_dialog' => '1', 'name_en' => '', 'name_ar' => ''])
            ->assertRedirect('/departments/create?dialog=1')
            ->assertSessionHasErrors('name_en');
    }

    public function test_redirect_to_another_form_keeps_dialog_flag(): void
    {
        $this->actingAs($this->admin())->post('/checklists', [
            '_dialog' => '1',
            'name_en' => 'Fryer weekly',
            'name_ar' => 'القلاية أسبوعي',
        ])->assertRedirectContains('/edit?dialog=1');
    }

    public function test_password_change_in_dialog_closes_to_home_with_message(): void
    {
        $this->actingAs($this->admin())->put('/password', [
            '_dialog' => '1',
            'current_password' => '1234',
            'password' => '5678',
            'password_confirmation' => '5678',
        ])
            ->assertOk()
            ->assertViewIs('dialog.close')
            ->assertViewHas('url', route('home'))
            ->assertSessionHas('ok', 'PasswordChanged');
    }

    public function test_technician_sees_password_message_after_home_redirect(): void
    {
        $technician = User::where('username', 'tech1')->firstOrFail();

        $this->actingAs($technician)->followingRedirects()->put('/password', [
            'current_password' => '1234',
            'password' => '5678',
            'password_confirmation' => '5678',
        ])
            ->assertOk()
            ->assertSee(__('PasswordChanged'));
    }

    public function test_regular_submit_is_unchanged(): void
    {
        $this->actingAs($this->admin())->post('/departments', [
            'name_en' => 'Bakery',
            'name_ar' => 'المخبز',
            'is_active' => '1',
        ])->assertRedirect('/departments');
    }
}
