<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\Branding;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DeveloperSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([DatabaseSeeder::class, DemoSeeder::class]);
    }

    private function user(string $username): User
    {
        return User::where('username', $username)->firstOrFail();
    }

    private function developer(): User
    {
        return User::factory()->role(Role::Admin)->create(['username' => 'dev', 'is_developer' => true]);
    }

    public function test_developer_settings_are_hidden_from_admins_and_other_roles(): void
    {
        $this->get('/dev/settings')->assertRedirect('/login');
        $this->actingAs($this->user('admin'))->get('/dev/settings')->assertForbidden();
        $this->actingAs($this->user('admin'))->put('/dev/settings', ['company_name_ar' => 'x'])->assertForbidden();
        $this->actingAs($this->user('coord'))->get('/dev/settings')->assertForbidden();
        $this->actingAs($this->user('tech1'))->get('/dev/settings')->assertForbidden();

        $this->actingAs($this->user('admin'))->get('/')->assertOk()->assertDontSee('/dev/settings');
    }

    public function test_developer_can_save_branding_and_it_shows_everywhere(): void
    {
        $dev = $this->developer();

        $this->actingAs($dev)->get('/dev/settings')->assertOk()->assertSee(__('CompanyNameAr'));
        $this->actingAs($dev)->put('/dev/settings', [
            'company_name_ar' => 'زهور الشفا',
            'company_name_en' => 'Zohor Al Shafa',
            'company_address' => 'Amman',
            'company_phone' => '0790000000',
        ])->assertRedirect('/dev/settings');

        $this->assertSame('زهور الشفا', Setting::get(Setting::COMPANY_NAME_AR));
        $this->assertDatabaseHas('activity_logs', ['action' => 'branding_updated', 'user_id' => $dev->id]);

        auth()->logout();
        $this->withUnencryptedCookie('cmms_locale', 'en')->get('/login')->assertOk()->assertSee('Zohor Al Shafa');
        $this->withUnencryptedCookie('cmms_locale', 'ar')->get('/login')->assertOk()->assertSee('زهور الشفا');
        $this->actingAs($this->user('admin'))->withUnencryptedCookie('cmms_locale', 'en')->get('/')->assertOk()
            ->assertSee('<title>'.__('Dashboard', [], 'en').' - Zohor Al Shafa</title>', false)
            ->assertDontSee('/dev/settings', false);

        $this->actingAs($dev)->get('/dev/settings')->assertOk()->assertSee('/dev/settings');
        $this->withUnencryptedCookie('cmms_locale', 'ar')->get('/manifest.webmanifest')->assertOk()->assertJsonFragment(['short_name' => 'زهور الشفا']);
        $this->withUnencryptedCookie('cmms_locale', 'en')->get('/manifest.webmanifest')->assertOk()->assertJsonFragment(['short_name' => 'Zohor Al Shafa']);

        $pdf = $this->actingAs($this->user('admin'))->get('/reports/requests/export/pdf');
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $html = $this->actingAs($this->user('admin'))->withUnencryptedCookie('cmms_locale', 'en')->get('/reports/summary')->assertOk();
        $html->assertSee('Zohor Al Shafa — زهور الشفا');
    }

    public function test_branding_falls_back_to_other_language_then_default_name(): void
    {
        $this->assertSame(__('AppName', [], 'en'), Branding::name('en'));
        $this->assertSame(__('AppName', [], 'ar'), Branding::name('ar'));

        Setting::set(Setting::COMPANY_NAME_AR, 'زهور الشفا');
        $this->assertSame('زهور الشفا', Branding::name('en'));
        $this->assertSame('زهور الشفا', Branding::fullName());
    }

    public function test_logo_upload_and_removal(): void
    {
        $dev = $this->developer();
        $file = UploadedFile::fake()->image('logo.png', 200, 80);

        $this->actingAs($dev)->put('/dev/settings', ['logo' => $file])->assertRedirect('/dev/settings');
        $url = Setting::get(Setting::COMPANY_LOGO);
        $this->assertStringStartsWith('/uploads/branding/', $url);
        $this->assertFileExists(public_path(ltrim($url, '/')));
        auth()->logout();
        $this->get('/login')->assertOk()->assertSee($url);

        $this->actingAs($dev)->put('/dev/settings', ['remove_logo' => '1'])->assertRedirect('/dev/settings');
        $this->assertNull(Setting::get(Setting::COMPANY_LOGO));
        $this->assertFileDoesNotExist(public_path(ltrim($url, '/')));

        $this->actingAs($dev)->put('/dev/settings', ['logo' => UploadedFile::fake()->create('evil.php', 10)])->assertSessionHasErrors('logo');
        File::deleteDirectory(public_path('uploads/branding'));
    }

    public function test_developer_account_is_invisible_to_admins(): void
    {
        $dev = $this->developer();
        $admin = $this->user('admin');

        $this->actingAs($admin)->get('/users')->assertOk()->assertDontSee('dev</td>', false);
        $this->actingAs($admin)->get('/users/'.$dev->id.'/edit')->assertNotFound();
        $this->actingAs($admin)->put('/users/'.$dev->id, ['full_name' => 'x', 'username' => 'dev', 'role' => 'Employee'])->assertNotFound();
        $this->assertTrue($dev->fresh()->isDeveloper());

        $this->actingAs($dev)->get('/users')->assertOk()->assertSee('dev</td>', false);
        $this->actingAs($dev)->get('/users/'.$dev->id.'/edit')->assertOk();
    }

    public function test_admin_cannot_grant_developer_flag_through_user_form(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->post('/users', [
            'full_name' => 'Sneaky', 'username' => 'sneaky', 'role' => 'Admin', 'password' => '1234', 'is_developer' => '1',
        ])->assertRedirect('/users');

        $this->assertFalse($this->user('sneaky')->isDeveloper());
    }

    public function test_artisan_command_creates_and_promotes_developer(): void
    {
        $this->artisan('cmms:developer', ['username' => 'dev', '--name' => 'Othman', '--password' => 'secret1'])->assertSuccessful();
        $dev = $this->user('dev');
        $this->assertTrue($dev->isDeveloper());
        $this->assertSame(Role::Admin, $dev->role);
        $this->post('/login', ['username' => 'dev', 'password' => 'secret1'])->assertRedirect();

        $this->artisan('cmms:developer', ['username' => 'coord'])->assertSuccessful();
        $this->assertTrue($this->user('coord')->isDeveloper());
        $this->assertSame(Role::Admin, $this->user('coord')->role);

        $this->artisan('cmms:developer', ['username' => 'nobody'])->expectsQuestion('Password for the new developer account', '')->assertFailed();
    }
}
