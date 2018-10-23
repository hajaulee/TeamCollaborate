<?php

namespace App\Http\Controllers\User\Auth;

use App\Model\SocialAccount;
use App\Model\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class OAuthController extends Controller
{
    use AuthenticatesUsers;

    public function redirectToProvider()
    {
        return Socialite::driver('facebook')->stateless()->redirect();
    }

    public function handleProviderCallback(Request $request)
    {
        $socialUser = Socialite::driver('facebook')->stateless()->user();

        $socialAccount = SocialAccount::where('facebook_id', $socialUser->getId())->first();
        if ($socialAccount) {
            if ($socialAccount->user()->where('status', User::ACTIVE)->where('deleted_at', null)->first()) {
                $socialAccount->update([
                    'access_token' => $socialUser->token,
                    'refresh_token' => $socialUser->refreshToken,
                ]);
                $user = $socialAccount->user;
            } else {
                return view('user.fb-login-popup', ['token' => null]);
            }
        } else {
            $user = User::where('email', $socialUser->getEmail())->first();
            if (!$user) {
                $user = $this->createUser($socialUser);

                $user->socialAccount()->create([
                    'facebook_id' => $socialUser->getId(),
                    'access_token' => $socialUser->token,
                    'refresh_token' => $socialUser->refreshToken,
                ]);
            } elseif (User::where('email', $socialUser->getEmail())->where('status', User::ACTIVE)
                ->where('deleted_at', null)->first()) {
                $user->socialAccount()->create([
                    'facebook_id' => $socialUser->getId(),
                    'access_token' => $socialUser->token,
                    'refresh_token' => $socialUser->refreshToken,
                ]);
            } else {
                return view('user.fb-login-popup', ['token' => null]);
            }
        }

        $token = $this->guard()->login($user);

        return view('user.fb-login-popup', ['token' => $token]);
    }

    public function createUser($socialUser)
    {
        return User::create([
            'name' => $socialUser->getName(),
            'email' => $socialUser->getEmail(),
            'avatar' => $socialUser->getAvatar()
        ]);
    }
}