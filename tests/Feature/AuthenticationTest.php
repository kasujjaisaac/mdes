<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_available(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Masaka Diocese')
            ->assertSee('Masaka Diocesan Education Secretariat Portal')
            ->assertSee('Sign in to continue');
    }

    public function test_reset_link_is_sent_only_to_the_registered_email(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $user=User::factory()->create(['email'=>'registered@example.test']);
        $this->get(route('password.request'))->assertOk()->assertSee('Send reset link');
        $this->post(route('password.email'),['email'=>$user->email])->assertSessionHasNoErrors();
        \Illuminate\Support\Facades\Notification::assertSentTo($user,\Illuminate\Auth\Notifications\ResetPassword::class,function($notification) use($user){
            $this->get(route('password.reset',['token'=>$notification->token,'email'=>$user->email]))->assertOk()->assertSee('Choose a new password');
            return true;
        });
        $this->post(route('password.email'),['email'=>'unknown@example.test'])->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->assertDatabaseMissing('password_reset_tokens',['email'=>'unknown@example.test']);
    }

    public function test_password_reset_token_can_only_be_used_once(): void
    {
        $user=User::factory()->create(['password'=>Hash::make('old-password')]);
        $token=\Illuminate\Support\Facades\Password::createToken($user);
        $payload=['token'=>$token,'email'=>$user->email,'password'=>'new-password-2026','password_confirmation'=>'new-password-2026'];
        $this->post(route('password.update'),$payload)->assertSessionHasNoErrors()->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('new-password-2026',$user->fresh()->password));
        $this->post(route('password.update'),$payload)->assertSessionHasErrors('email');
    }

    public function test_inactive_accounts_are_not_sent_reset_links(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $user=User::factory()->create(['status'=>'suspended']);
        $this->post(route('password.email'),['email'=>$user->email])->assertSessionHas('status');
        \Illuminate\Support\Facades\Notification::assertNothingSent();
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@mdsiss.test',
            'password' => Hash::make('secret-password'),
        ]);

        $this->post('/login', [
            'email' => 'admin@mdsiss.test',
            'password' => 'secret-password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        User::factory()->create([
            'email' => 'admin@mdsiss.test',
            'password' => Hash::make('secret-password'),
        ]);

        $this->from('/login')->post('/login', [
            'email' => 'admin@mdsiss.test',
            'password' => 'wrong-password',
        ])->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }
}
