<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Tweet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use App\Models\User;

class ProfileController extends Controller
{
  /**
   * Display the specified resource.
   */
  public function show(User $user)
  {
    if (auth()->user()->is($user)) {
      $tweets = Tweet::query()
        ->where('user_id', $user->id)  // 自分のツイート
        ->orWhereIn('user_id', $user->follows->pluck('id')) // フォローしているユーザーのツイート
        ->latest()
        ->paginate(10);
    } else {
      // 他のユーザーの場合、そのユーザーのツイートのみを取得
      $tweets = $user
        ->tweets()
        ->latest()
        ->paginate(10);
    }

    // ユーザーのフォロワーとフォローしているユーザーを取得
    $user->load(['follows', 'followers']);
    return view('profile.show', compact('user', 'tweets'));

    // $user は表示したいユーザーのインスタンス
    $posts = $user->posts()->latest()->get(); // そのユーザーの投稿を取得
    // ビューに $user と $posts を渡す
    return view('users.show', compact('user', 'posts'));
  }

  /**
   * Display the user's profile form.
   */
  public function edit(Request $request): View
  {
    return view('profile.edit', [
      'user' => $request->user(),
    ]);
  }

  /**
   * Update the user's profile information.
   */
  public function update(ProfileUpdateRequest $request): RedirectResponse
  {
    $request->user()->fill($request->validated());

    if ($request->user()->isDirty('email')) {
      $request->user()->email_verified_at = null;
    }

    $request->user()->save();

    return Redirect::route('profile.edit')->with('status', 'profile-updated');
  }

  /**
   * Delete the user's account.
   */
  public function destroy(Request $request): RedirectResponse
  {
    $request->validateWithBag('userDeletion', [
      'password' => ['required', 'current_password'],
    ]);

    $user = $request->user();

    Auth::logout();

    $user->delete();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return Redirect::to('/');
  }

  public function updateBio(Request $request)
  {
    // 1. バリデーション（データの検証）
    $validated = $request->validate([
      // bio（自己紹介文）はテキスト型で、最大500文字を許可
      'bio' => ['nullable', 'string', 'max:500'],
    ]);

    // 2. ログインユーザーのデータを更新
    $user = $request->user();

    $user->fill([
      'bio' => $validated['bio'],
    ]);

    $user->save();

    // 3. ユーザーを前のページに戻し、成功メッセージをセッションに格納
    return back()->with('status', 'プロフィール（自己紹介文）を更新しました。');
  }
}
