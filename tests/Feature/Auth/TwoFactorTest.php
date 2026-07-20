<?php

use App\Mail\TwoFactorCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

it('exige un code à 6 chiffres envoyé par e-mail quand la 2FA est activée', function () {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'radio@example.com',
        'password' => 'MotDePasse!2026',
        'two_factor_enabled' => true,
    ]);

    $response = $this->post(route('admin.login.store'), [
        'email' => 'radio@example.com',
        'password' => 'MotDePasse!2026',
    ]);

    $response->assertRedirect(route('admin.login.two-factor'));
    $this->assertGuest();
    Mail::assertSent(TwoFactorCodeMail::class, fn ($mail) => $mail->hasTo($user->email));

    expect($user->fresh()->two_factor_code)->not->toBeNull();
});

it('connecte l’utilisateur après vérification du bon code', function () {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'radio@example.com',
        'password' => 'MotDePasse!2026',
        'two_factor_enabled' => true,
    ]);

    $this->post(route('admin.login.store'), [
        'email' => 'radio@example.com',
        'password' => 'MotDePasse!2026',
    ]);

    $sentCode = null;
    Mail::assertSent(TwoFactorCodeMail::class, function ($mail) use (&$sentCode) {
        $sentCode = $mail->code;

        return true;
    });

    $response = $this->post(route('admin.login.two-factor.verify'), [
        'code' => $sentCode,
    ]);

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($user->fresh());
});

it('refuse un code de vérification incorrect', function () {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'radio@example.com',
        'password' => 'MotDePasse!2026',
        'two_factor_enabled' => true,
    ]);

    $this->post(route('admin.login.store'), [
        'email' => 'radio@example.com',
        'password' => 'MotDePasse!2026',
    ]);

    $response = $this->post(route('admin.login.two-factor.verify'), [
        'code' => '000000',
    ]);

    $response->assertSessionHasErrors('code');
    $this->assertGuest();
});
